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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractIndexHandler implements IndexHandlerInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly SearchIndexServiceInterface $searchIndexService,
        protected readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly IndexMappingServiceInterface $indexMappingService,
    ) {
    }

    public function updateMapping(
        mixed $context = null,
        bool $forceCreateIndex = false,
        ?array $mappingProperties = null
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
            //try recreating index
            $this->reindexMapping($context, $mappingProperties);
        }
    }

    /**
     * @throws Exception
     */
    public function reindexMapping(
        ?ClassDefinition $context = null,
        ?array $mappingProperties = null
    ): void {
        $alias = $this->getAliasIndexName($context);
        $mappingProperties = $mappingProperties ?: $this->extractMappingProperties($context);

        if (!$this->searchIndexService->existsAlias($alias)) {
            $this->updateMapping(
                context: $context,
                mappingProperties: $mappingProperties
            );
        } else {
            $reindexResult = $this->searchIndexService->reindex(
                $alias,
                $mappingProperties
            );

            if ($reindexResult === ReindexResult::MAPPING_INCOMPATIBLE) {
                // The new mapping cannot be applied to the existing documents (e.g.
                // after a field type change): recreate the index with the new mapping;
                // its content is re-populated from the index queue. Genuine reindex
                // errors (unreachable cluster, timeouts) are thrown by reindex() and
                // propagate — recreating the live index in reaction to a transient
                // failure would destroy all indexed data.
                $this->logger->warning(sprintf(
                    'Recreating index for alias "%s": the new mapping is incompatible with the indexed documents',
                    $alias
                ));
                $this->updateMapping($context, true, $mappingProperties);
            }
        }

        $this->createGlobalIndexAliases($context);
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
            ksort($normalizedValue);
        }

        return $normalizedValue;
    }

    abstract protected function extractMappingProperties(mixed $context = null): array;

    abstract protected function getAliasIndexName(mixed $context = null): string;

    /**
     * @throws JsonException
     */
    private function doUpdateMapping(mixed $context): void
    {
        $response = $this->searchIndexService->putMapping(
            [
                'index' => $this->getCurrentFullIndexName($context),
                'body' => [
                    '_source' => [
                        'enabled' => true,
                    ],
                    'properties' => $this->extractMappingProperties($context),
                ],
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
