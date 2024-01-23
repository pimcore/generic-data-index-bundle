<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\MappingHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

abstract class AbstractMappingHandler implements MappingHandlerInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly OpenSearchService $openSearchService,
        protected readonly SearchIndexConfigService $searchIndexConfigService,
    )
    {
    }

    public function updateMapping(mixed $context = null, bool $forceCreateIndex = false): void
    {
        $aliasName = $this->getIndexAliasName($context);

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

    private function createIndex(mixed $context, string $aliasName): void
    {
        $fullIndexName = $this->getCurrentFullIndexName($context);

        $this
            ->openSearchService
            ->createIndex($fullIndexName)
            ->addAlias($aliasName, $fullIndexName)
        ;

    }

    abstract protected function extractMappingProperties(mixed $context = null): array;
    abstract protected function getIndexAliasName(mixed $context = null): string;

    public function getCurrentFullIndexName(mixed $context = null): string
    {
        $indexName = $this->getIndexAliasName($context);
        $currentIndexVersion = $this->openSearchService->getCurrentIndexVersion($indexName);

        return $indexName . '-' . ($currentIndexVersion === 'even' ? 'even' : 'odd');
    }


    protected function doUpdateMapping(mixed $context): void
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
}