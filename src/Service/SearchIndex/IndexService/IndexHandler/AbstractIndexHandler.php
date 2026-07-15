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
            try {
                $this->searchIndexService->reindex(
                    $alias,
                    $mappingProperties
                );
            } catch (Exception $e) {
                try {
                    $this->updateMapping($context, true, $mappingProperties);
                } catch (Exception $fallbackException) {
                    // Both the reindex and the fallback recreation failed: rethrow so the
                    // failure reaches the caller instead of the mapping checksum being
                    // stored as if the reindex had succeeded.
                    $this->logger->error(sprintf(
                        'Reindexing failed due to following error: %s (initial reindex failure: %s)',
                        $fallbackException,
                        $e->getMessage()
                    ));

                    throw $fallbackException;
                }
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
        return crc32(json_encode($properties, JSON_THROW_ON_ERROR));
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
