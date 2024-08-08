<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Exception;
use JsonException;
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
        if (!$this->searchIndexService->existsAlias($alias)) {
            $this->updateMapping(
                context: $context,
                mappingProperties: $mappingProperties
            );
        } else {
            $this->searchIndexService->reindex(
                $alias,
                $mappingProperties ?: $this->extractMappingProperties($context)
            );
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
