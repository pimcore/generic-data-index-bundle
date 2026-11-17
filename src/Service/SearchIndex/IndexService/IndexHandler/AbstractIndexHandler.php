<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Closure;
use Exception;
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\TotalFieldsLimitExceededException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLockInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingFieldCountService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingFieldCountServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\TotalFieldsLimitMessageService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\TotalFieldsLimitMessageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractIndexHandler implements IndexHandlerInterface
{
    use LoggerAwareTrait;

    private const MAX_REINDEX_ATTEMPTS = 3;

    /** Created on first use for subclasses that call the constructor with the four arguments of 2026.3.2. */
    private ?IndexCopyServiceInterface $defaultIndexCopyService = null;

    /** Created on first use: the counter is stateless, so a subclass not passing one costs nothing. */
    private ?MappingFieldCountServiceInterface $defaultFieldCountService = null;

    private ?TotalFieldsLimitMessageServiceInterface $defaultLimitMessageService = null;

    private ?bool $reindexMappingOverridden = null;

    private bool $runningReindexMappingOverride = false;

    public function __construct(
        protected readonly SearchIndexServiceInterface $searchIndexService,
        protected readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly IndexMappingServiceInterface $indexMappingService,
        private readonly ?IndexCopyServiceInterface $indexCopyService = null,
        private readonly ?IndexLockInterface $indexLock = null,
        private readonly ?MappingFieldCountServiceInterface $mappingFieldCountService = null,
        private readonly ?TotalFieldsLimitMessageServiceInterface $limitMessageService = null,
    ) {
        if ($indexCopyService === null) {
            trigger_deprecation(
                'pimcore/generic-data-index-bundle',
                '2026.3.3',
                'Calling the constructor of %s without an %s is deprecated; the argument will be required in the '
                . 'next major version.',
                static::class,
                IndexCopyServiceInterface::class
            );
        }
        if ($indexLock === null) {
            trigger_deprecation(
                'pimcore/generic-data-index-bundle',
                '2026.3.5',
                'Calling the constructor of %s without an %s is deprecated; the argument will be required in the '
                . 'next major version. Without it, mapping updates of the same index can overlap.',
                static::class,
                IndexLockInterface::class
            );
        }
    }

    /**
     * @throws ReindexFailedException
     */
    public function updateMapping(
        mixed $context = null,
        bool $forceCreateIndex = false,
        ?array $mappingProperties = null
    ): void {
        $mappingProperties = $this->assertFieldCountWithinLimit($context, $mappingProperties);

        $this->underIndexLock(
            $this->getAliasIndexName($context),
            fn () => $this->doUpdateMappingFull($context, $forceCreateIndex, $mappingProperties, 0)
        );
    }

    /**
     * Depth-aware implementation of updateMapping(); called internally so that
     * doReindexMapping() can forward the current recursion depth without exposing
     * the counter through the public interface.
     */
    private function doUpdateMappingFull(
        mixed $context,
        bool $forceCreateIndex,
        ?array $mappingProperties,
        int $reindexDepth
    ): void {
        $aliasName = $this->getAliasIndexName($context);
        $mappingProperties = $mappingProperties ?: $this->extractMappingProperties($context);

        if ($forceCreateIndex || !$this->searchIndexService->existsAlias($aliasName)) {
            // Recover from corrupted state: both -even and -odd may exist without an alias
            // (e.g. after a failed reindex). Delete both and recreate cleanly.
            if (!$this->searchIndexService->existsAlias($aliasName)) {
                $versions = [DefaultSearchService::INDEX_VERSION_EVEN, DefaultSearchService::INDEX_VERSION_ODD];
                foreach ($versions as $version) {
                    $this->searchIndexService->deleteIndex($aliasName . '-' . $version, true);
                }

                // While the alias is missing, indexing traffic can auto-create a concrete
                // index carrying the exact alias name; attaching the alias would then fail
                // with invalid_alias_name_exception. Its data is derived from the database
                // and gets rebuilt by the reindex following the recreation.
                if ($this->searchIndexService->existsIndex($aliasName)) {
                    $this->logger->warning(
                        sprintf('Deleting index "%s" occupying the alias name before recreation', $aliasName)
                    );
                    $this->searchIndexService->deleteIndex($aliasName, true);
                }
            }

            // The index carries its mapping from the create request on, so a mapping the engine refuses leaves
            // no index - and no alias - behind. The putMapping below then applies it to an index that already
            // has it, which is what keeps the retry and escalation of a refused in-place update unchanged.
            $this->createIndex($context, $aliasName, $mappingProperties);
        }

        // A class-definition mapping that drops a live property cannot go in place: putMapping keeps the property.
        if (!$forceCreateIndex && $this->mappingRemovesLiveProperty($context, $mappingProperties)) {
            $this->doReindexMapping($context, $mappingProperties, $reindexDepth + 1);

            return;
        }

        //updating mapping without recreating index
        try {
            $this->doUpdateMapping($context);
        } catch (Exception $e) {
            $this->logger->info($e);
            //try recreating index — ReindexFailedException propagates to the caller.
            $this->doReindexMapping($context, $mappingProperties, $reindexDepth + 1, $e);
        }
    }

    /**
     * The mapping is applied in place unless a class-definition mapping drops a property of the live index, which
     * takes the copy; other indices and a handler overriding reindexMapping() keep their behaviour.
     *
     * @internal returns a model that is not part of the public API
     *
     * @throws Exception
     */
    public function updateMappingWithResult(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null
    ): MappingUpdateResult {
        $mappingProperties = $this->assertFieldCountWithinLimit($context, $mappingProperties);

        return $this->underIndexLock(
            $this->getAliasIndexName($context),
            fn () => $this->doUpdateMappingWithResult($context, $mappingProperties)
        );
    }

    /**
     * @throws Exception
     */
    private function doUpdateMappingWithResult(
        ?ClassDefinition $context,
        ?array $mappingProperties
    ): MappingUpdateResult {
        if ($this->isReindexMappingOverridden()
            || !$this->searchIndexService->existsAlias($this->getAliasIndexName($context))
            || $this->mappingRemovesLiveProperty($context, $mappingProperties)
        ) {
            return $this->doReindexMappingWithResult($context, $mappingProperties);
        }

        try {
            $this->doUpdateMapping($context);
        } catch (Exception $e) {
            $this->logger->info($e);

            // putMapping refused the change (a type change, say): the copy handles it as it always did.
            return $this->doReindexMapping($context, $mappingProperties, 1, $e);
        }

        return new MappingUpdateResult();
    }

    /**
     * Whether the live index holds a class field the new mapping of a class definition no longer has. Only
     * standard_fields is the class's own surface: system_fields belongs to the bundle, custom_fields to
     * whichever bundle declared it, and neither category carries a dynamic key, so a field written into the
     * documents but declared by no config — the checksum, or the lock state once an object is locked — reads
     * as a removal forever and would otherwise take the copy on every mapping change. The inherited field
     * values are written into the documents of an inheriting class without the mapping declaring them, so
     * they are left out the same way.
     *
     * @throws JsonException
     */
    private function mappingRemovesLiveProperty(mixed $context, ?array $mappingProperties): bool
    {
        $alias = $this->getAliasIndexName($context);
        if (!$context instanceof ClassDefinition || !$this->searchIndexService->existsAlias($alias)) {
            return false;
        }

        $mappingProperties = $mappingProperties ?: $this->extractMappingProperties($context);
        $diff = $this->getIndexCopyService()->diff($alias, $mappingProperties);
        if ($diff->isLiveMappingUnreadable()) {
            // Nothing was compared: fail closed, so a removal cannot slip through an in-place update.
            $this->logger->debug(sprintf(
                'Copying index "%s": the live mapping of class "%s" could not be read',
                $alias,
                $context->getName()
            ));

            return true;
        }
        foreach ($diff->getRemovedPathSegments() as $segments) {
            if ($segments[0] !== FieldCategory::STANDARD_FIELDS->value
                || ($segments[1] ?? null) === FieldCategory::INHERITED_FIELDS->value) {
                continue;
            }
            $this->logger->debug(sprintf(
                'Copying index "%s": the new mapping of class "%s" no longer has %s',
                $alias,
                $context->getName(),
                implode('.', $segments)
            ));

            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     * @throws ReindexFailedException
     */
    public function reindexMapping(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null
    ): void {
        $mappingProperties = $this->assertFieldCountWithinLimit($context, $mappingProperties);

        $this->underIndexLock(
            $this->getAliasIndexName($context),
            fn () => $this->doReindexMapping($context, $mappingProperties, 0)
        );
    }

    /**
     * A subclass that overrides reindexMapping() (written against 2026.3.2) keeps being called; its outcome is unknown,
     * so the result asks the caller for a rebuild from the database.
     *
     * @internal returns a model that is not part of the public API
     *
     * @throws Exception
     */
    public function reindexMappingWithResult(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null
    ): MappingUpdateResult {
        $mappingProperties = $this->assertFieldCountWithinLimit($context, $mappingProperties);

        return $this->underIndexLock(
            $this->getAliasIndexName($context),
            fn () => $this->doReindexMappingWithResult($context, $mappingProperties)
        );
    }

    /**
     * @throws Exception
     */
    private function doReindexMappingWithResult(
        ?ClassDefinition $context,
        ?array $mappingProperties
    ): MappingUpdateResult {
        // An override that delegates back to this method runs the copy itself instead of calling the override again.
        if ($this->isReindexMappingOverridden() && !$this->runningReindexMappingOverride) {
            $this->runningReindexMappingOverride = true;

            try {
                $this->reindexMapping($context, $mappingProperties);
            } finally {
                $this->runningReindexMappingOverride = false;
            }

            return new MappingUpdateResult(outcomeUnknown: true);
        }

        return $this->doReindexMapping($context, $mappingProperties, 0);
    }

    /**
     * @throws Exception
     * @throws ReindexFailedException
     */
    private function doReindexMapping(
        ?ClassDefinition $context,
        ?array $mappingProperties,
        int $depth,
        ?\Throwable $cause = null
    ): MappingUpdateResult {
        if ($depth >= self::MAX_REINDEX_ATTEMPTS) {
            throw new ReindexFailedException(
                'Max reindex attempts reached, aborting to prevent infinite recursion.',
                0,
                $cause
            );
        }

        $alias = $this->getAliasIndexName($context);
        $this->refreshIndexLock($alias);
        $mappingProperties = $mappingProperties ?: $this->extractMappingProperties($context);

        if (!$this->searchIndexService->existsAlias($alias)) {
            $this->doUpdateMappingFull(
                context: $context,
                forceCreateIndex: false,
                mappingProperties: $mappingProperties,
                reindexDepth: $depth
            );
            $result = new MappingUpdateResult(recreated: true);
        } else {
            // Fields whose old values do not fit the new mapping are left out of the copy, so a
            // field type change no longer empties the live index; the rebuild from the database
            // fills them. Genuine errors (unreachable cluster, timeouts) are thrown and propagate.
            $copy = $this->getIndexCopyService()->copy($alias, $mappingProperties);
            $result = new MappingUpdateResult($copy->getStrippedPaths(), $copy->getKeptPaths());

            if (!$copy->isSuccessful()) {
                // Last resort: the copy failed on fields that could not be left out. Recreate
                // the index with the new mapping; its content is re-populated from the queue.
                $this->logger->warning(sprintf(
                    'Recreating index for alias "%s": the new mapping is incompatible with the indexed documents',
                    $alias
                ));
                $this->refreshIndexLock($alias);
                $this->doUpdateMappingFull($context, true, $mappingProperties, $depth);
                $result = new MappingUpdateResult($copy->getStrippedPaths(), $copy->getKeptPaths(), true);
            }
        }

        $this->createGlobalIndexAliases($context);

        return $result;
    }

    /**
     * @internal returns a model that is not part of the public API
     *
     * @throws JsonException
     */
    public function getMappingDiff(?ClassDefinition $context = null): MappingDiff
    {
        $alias = $this->getAliasIndexName($context);
        if (!$this->searchIndexService->existsAlias($alias)) {
            return new MappingDiff([]);
        }

        return $this->getIndexCopyService()->diff($alias, $this->extractMappingProperties($context));
    }

    public function deleteIndex(mixed $context = null): void
    {
        $this->underIndexLock(
            $this->getAliasIndexName($context),
            fn () => $this->searchIndexService->deleteIndex($this->getCurrentFullIndexName($context))
        );
    }

    public function getCurrentFullIndexName(mixed $context = null): string
    {
        $indexName = $this->getAliasIndexName($context);
        $currentIndexVersion = $this->searchIndexService->getCurrentIndexVersion($indexName);

        return $indexName . '-' . ($currentIndexVersion === 'even' ? 'even' : 'odd');
    }

    public function getMappingProperties(mixed $context): array
    {
        return $this->extractMappingProperties($context);
    }

    /**
     * @throws JsonException
     */
    public function getClassMappingCheckSum(array $properties): int
    {
        // The workflowPlaces mapping depends on the configured workflows, not on the class: it has its own
        // checksum and must not make every class look changed (and fully reindexed by the deployment commands).
        unset($properties[FieldCategory::SYSTEM_FIELDS->value]['properties'][SystemField::WORKFLOW_PLACES->value]);

        return crc32(json_encode($this->normalizeForCheckSum($properties), JSON_THROW_ON_ERROR));
    }

    private function normalizeForCheckSum(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalizedValue = [];
        foreach ($value as $key => $item) {
            $normalizedValue[$key] = $this->normalizeForCheckSum($item);
        }

        if (!array_is_list($normalizedValue)) {
            // SORT_STRING: the default SORT_REGULAR compares numeric keys numerically,
            // so distinct keys like 1 and '01' would compare equal and keep their
            // insertion order instead of being sorted deterministically.
            ksort($normalizedValue, SORT_STRING);
        }

        return $normalizedValue;
    }

    private function isReindexMappingOverridden(): bool
    {
        if ($this->reindexMappingOverridden === null) {
            $declaringClass = (new ReflectionMethod($this, 'reindexMapping'))->getDeclaringClass()->getName();
            $this->reindexMappingOverridden = $declaringClass !== self::class;
        }

        return $this->reindexMappingOverridden;
    }

    /**
     * Every mapping update of an index runs under the lock of its alias, so two processes never copy or recreate
     * the same index at the same time; the second one waits. The retries of an update and the recreation as the
     * last resort run inside the same lock.
     *
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    private function underIndexLock(string $aliasName, Closure $operation): mixed
    {
        return $this->indexLock === null ? $operation() : $this->indexLock->run($aliasName, $operation);
    }

    private function refreshIndexLock(string $aliasName): void
    {
        $this->indexLock?->refresh($aliasName);
    }

    /**
     * Refuses a mapping declaring more fields than the index allows, before the engine is asked for it, and
     * returns the mapping properties the refusal was decided on so they are extracted once.
     *
     * The engine's own check cannot be relied on: it runs after the mapping has been parsed into the heap of the
     * cluster manager, so a mapping far enough over the limit kills that node instead of answering with an error
     * (measured: a ~755,000 field classification store mapping on a 1 GB heap died in ObjectMapper$TypeParser).
     * Counted in the unit the engine checks, see {@see MappingFieldCountServiceInterface::countFields()}.
     *
     * @param array<string, mixed>|null $mappingProperties
     *
     * @return array<string, mixed>
     *
     * @throws TotalFieldsLimitExceededException
     */
    private function assertFieldCountWithinLimit(mixed $context, ?array $mappingProperties): array
    {
        $mappingProperties = $mappingProperties ?: $this->extractMappingProperties($context);

        $limit = $this->getConfiguredTotalFieldsLimit();
        if ($limit === null) {
            return $mappingProperties;
        }

        $countedFields = $this->getMappingFieldCountService()->countFields($mappingProperties);
        if ($countedFields <= $limit) {
            return $mappingProperties;
        }

        throw new TotalFieldsLimitExceededException(
            $this->getLimitMessageService()->forFieldCount(
                $this->getAliasIndexName($context),
                $countedFields,
                $limit,
                $this->getMappingFieldCountService()->getLargestContributor($mappingProperties)
            )
        );
    }

    /** Null when the configuration names no limit: then the engine is the only one who can refuse. */
    private function getConfiguredTotalFieldsLimit(): ?int
    {
        $limit = $this->searchIndexConfigService->getIndexSettings()['index']['mapping']['total_fields']['limit']
            ?? null;

        return is_numeric($limit) ? (int) $limit : null;
    }

    private function getMappingFieldCountService(): MappingFieldCountServiceInterface
    {
        return $this->mappingFieldCountService
            ?? $this->defaultFieldCountService
            ??= new MappingFieldCountService();
    }

    private function getLimitMessageService(): TotalFieldsLimitMessageServiceInterface
    {
        return $this->limitMessageService
            ?? $this->defaultLimitMessageService
            ??= new TotalFieldsLimitMessageService();
    }

    private function getIndexCopyService(): IndexCopyServiceInterface
    {
        if ($this->indexCopyService !== null) {
            return $this->indexCopyService;
        }
        if ($this->defaultIndexCopyService === null) {
            $this->defaultIndexCopyService = new IndexCopyService(
                searchIndexService: $this->searchIndexService,
                mappingDiffService: new MappingDiffService(),
                pimcoreGenericDataIndexLogger: $this->logger ?? new NullLogger(),
                indexLock: $this->indexLock
            );
        }

        return $this->defaultIndexCopyService;
    }

    abstract protected function extractMappingProperties(mixed $context = null): array;

    abstract protected function getAliasIndexName(mixed $context = null): string;

    /**
     * @throws JsonException
     */
    private function doUpdateMapping(mixed $context): void
    {
        $mappingProperties = $this->extractMappingProperties($context);
        $body = [
            '_source' => ['enabled' => true],
        ];
        if (!empty($mappingProperties)) {
            $body['properties'] = $mappingProperties;
        }
        $response = $this->searchIndexService->putMapping(
            [
                'index' => $this->getCurrentFullIndexName($context),
                'body' => $body,
            ]
        );
        $this->logger->debug(json_encode($response, JSON_THROW_ON_ERROR));
    }

    /**
     * Creates the index of the alias with the given mapping.
     *
     * A live index behind the alias is not touched until the replacement stands: it is built under the other
     * version name and the alias is moved onto it in one engine request. Deleting first is what turned a
     * refused mapping into the reported cascade - the live index was emptied, kept its aliases, and the
     * objects written into it afterwards got a dynamic mapping without "system_fields.fullPath.sort", so the
     * tree sort dropped them from an otherwise successful search.
     *
     * @param array<string, mixed>|null $mappingProperties
     */
    protected function createIndex(mixed $context, string $aliasName, ?array $mappingProperties = null): void
    {
        $mappingProperties = $mappingProperties ?: $this->extractMappingProperties($context);
        $liveIndexName = $this->getCurrentFullIndexName($context);
        $replacesLiveIndex = $this->searchIndexService->existsAlias($aliasName, $liveIndexName);

        $newIndexName = $replacesLiveIndex ? $this->getStandbyFullIndexName($liveIndexName) : $liveIndexName;

        $this->searchIndexService->createIndex($newIndexName, $mappingProperties);

        if ($replacesLiveIndex) {
            $this->searchIndexService->switchIndexAlias($aliasName, $newIndexName);
        } else {
            $this->searchIndexService->addAlias($aliasName, $newIndexName);
        }

        $this->createGlobalIndexAliases($context);
    }

    /** The other index of the odd/even family, the one the alias does not point at. */
    private function getStandbyFullIndexName(string $liveIndexName): string
    {
        return str_ends_with($liveIndexName, '-' . DefaultSearchService::INDEX_VERSION_EVEN)
            ? substr($liveIndexName, 0, -strlen(DefaultSearchService::INDEX_VERSION_EVEN))
                . DefaultSearchService::INDEX_VERSION_ODD
            : substr($liveIndexName, 0, -strlen(DefaultSearchService::INDEX_VERSION_ODD))
                . DefaultSearchService::INDEX_VERSION_EVEN;
    }

    protected function createGlobalIndexAliases(mixed $context = null): void
    {
    }
}
