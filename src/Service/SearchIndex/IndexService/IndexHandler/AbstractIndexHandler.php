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

use Exception;
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractIndexHandler implements IndexHandlerInterface
{
    use LoggerAwareTrait;

    private const MAX_REINDEX_ATTEMPTS = 3;

    /** Created on first use for subclasses that call the constructor with the four arguments of 2026.3.2. */
    private ?IndexCopyServiceInterface $defaultIndexCopyService = null;

    public function __construct(
        protected readonly SearchIndexServiceInterface $searchIndexService,
        protected readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly IndexMappingServiceInterface $indexMappingService,
        private readonly ?IndexCopyServiceInterface $indexCopyService = null,
    ) {
        if ($indexCopyService === null) {
            trigger_deprecation(
                'pimcore/generic-data-index-bundle',
                '2026.3.3',
                'Calling the constructor of %s without an %s is deprecated; the argument will be required in the '
                . 'next major version.',
                self::class,
                IndexCopyServiceInterface::class
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
        $this->doUpdateMappingFull($context, $forceCreateIndex, $mappingProperties, 0);
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

            $this->createIndex($context, $aliasName);
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
     * @throws Exception
     * @throws ReindexFailedException
     */
    public function reindexMapping(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null
    ): void {
        $this->reindexMappingWithResult($context, $mappingProperties);
    }

    /**
     * @throws Exception
     */
    public function reindexMappingWithResult(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null
    ): MappingUpdateResult {
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
                $this->doUpdateMappingFull($context, true, $mappingProperties, $depth);
                $result = new MappingUpdateResult($copy->getStrippedPaths(), $copy->getKeptPaths(), true);
            }
        }

        $this->createGlobalIndexAliases($context);

        return $result;
    }

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
        $this->searchIndexService->deleteIndex(
            $this->getCurrentFullIndexName($context)
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

    private function getIndexCopyService(): IndexCopyServiceInterface
    {
        return $this->indexCopyService ?? $this->defaultIndexCopyService ??= new IndexCopyService(
            $this->searchIndexService,
            new MappingDiffService(),
            $this->logger ?? new NullLogger()
        );
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

    protected function createIndex(mixed $context, string $aliasName): void
    {
        $fullIndexName = $this->getCurrentFullIndexName($context);

        $this
            ->searchIndexService
            ->createIndex($fullIndexName)
            ->addAlias($aliasName, $fullIndexName)
        ;

        $this->createGlobalIndexAliases($context);
    }

    protected function createGlobalIndexAliases(mixed $context = null): void
    {
    }
}
