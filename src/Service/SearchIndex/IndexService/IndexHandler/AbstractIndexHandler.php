<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

abstract class AbstractIndexHandler implements IndexHandlerInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly OpenSearchService $openSearchService,
        protected readonly SearchIndexConfigService $searchIndexConfigService,
    ) {
    }

    public function updateMapping(mixed $context = null, bool $forceCreateIndex = false): void
    {
        $aliasName = $this->getAliasIndexName($context);

        if ($forceCreateIndex || !$this->openSearchService->existsAlias($aliasName)) {
            $this->createIndex($context, $aliasName);
        }

        //updating mapping without recreating index
        try {
            $this->doUpdateMapping($context);
        } catch (Exception $e) {
            $this->logger->info($e);
            //try recreating index
            $this->openSearchService->reindex($aliasName, $this->extractMappingProperties($context));
        }
    }

    public function deleteIndex(mixed $context = null): void
    {
        $this->openSearchService->deleteIndex(
            $this->getCurrentFullIndexName()
        );
    }

    abstract protected function extractMappingProperties(mixed $context = null): array;

    abstract protected function getAliasIndexName(mixed $context = null): string;

    private function doUpdateMapping(mixed $context): void
    {
        $response = $this->openSearchService->putMapping(
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
        $this->logger->debug(json_encode($response));
    }


    private function createIndex(mixed $context, string $aliasName): void
    {
        $fullIndexName = $this->getCurrentFullIndexName($context);

        $this
            ->openSearchService
            ->createIndex($fullIndexName)
            ->addAlias($aliasName, $fullIndexName)
        ;

    }

    private function getCurrentFullIndexName(mixed $context = null): string
    {
        $indexName = $this->getAliasIndexName($context);
        $currentIndexVersion = $this->openSearchService->getCurrentIndexVersion($indexName);

        return $indexName . '-' . ($currentIndexVersion === 'even' ? 'even' : 'odd');
    }
}
