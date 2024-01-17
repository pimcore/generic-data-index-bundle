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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Exception;
use JsonException;
use OpenSearch\Namespaces\IndicesNamespace;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Normalizer\AssetNormalizer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Contracts\Service\Attribute\Required;

class AssetIndexService extends AbstractIndexService
{


    private function getIndices(): IndicesNamespace
    {
        return $this->openSearchClient->indices();
    }

    protected function getIndexName(ElementInterface $element): string
    {
        return $this->getAssetIndexName();
    }

    protected function getAssetIndexName(): string
    {
        return $this->searchIndexConfigService->getIndexName(IndexName::ASSET->value);
    }

    protected function getCurrentFullIndexName(): string
    {
        $indexName = $this->getAssetIndexName();
        $currentIndexVersion = $this->openSearchService->getCurrentIndexVersion($indexName);

        return $indexName . '-' . ($currentIndexVersion === 'even' ? 'even' : 'odd');
    }

    public function createIndex(): AssetIndexService
    {
        $fullIndexName = $this->getCurrentFullIndexName();

        $this->openSearchService
            ->createIndex($fullIndexName)
            ->addAlias($fullIndexName, $this->getAssetIndexName());

        return $this;
    }

    public function deleteIndex(): AssetIndexService
    {
        $this
            ->openSearchService
            ->deleteIndex($this->getCurrentFullIndexName())
            ->deleteIndex($this->getAssetIndexName());

        return $this;
    }

    public function extractMapping(): array
    {
        $mappingProperties = [
            FieldCategory::SYSTEM_FIELDS->value => [
                'properties' => $this->searchIndexConfigService
                    ->getSystemFieldsSettings(SearchIndexConfigService::SYSTEM_FIELDS_SETTINGS_ASSET),
            ],
            FieldCategory::STANDARD_FIELDS->value => [],
            FieldCategory::CUSTOM_FIELDS->value => [],
        ];
        //$extractMappingEvent = new ExtractMappingEvent($mappingProperties[FieldCategory::CUSTOM_FIELDS->value]);
        //$this->eventDispatcher->dispatch($extractMappingEvent);
        //$mappingProperties[FieldCategory::CUSTOM_FIELDS->value]['properties'] =
        //  $extractMappingEvent->getCustomFieldsMapping();

        return [
            'index' => $this->getAssetIndexName(),
            'body' => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $mappingProperties,
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public function updateMapping(bool $forceCreateIndex = false): AssetIndexService
    {

        if ($forceCreateIndex || !$this->getIndices()->existsAlias(['name' => $this->getAssetIndexName()])) {
            $this->createIndex();
        }

        try {
            $this->doUpdateMapping();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->openSearchService->reindex($this->getAssetIndexName(), $this->extractMapping());
        }

        return $this;
    }

    /**
     * @throws JsonException
     */
    protected function doUpdateMapping(): AssetIndexService
    {
        $mapping = $this->extractMapping();
        $response = $this->getIndices()->putMapping($mapping);
        $this->logger->debug(json_encode($response, JSON_THROW_ON_ERROR));

        return $this;
    }

    #[Required]
    public function setElementNormalizer(AssetNormalizer $elementNormalizer): void
    {
        $this->elementNormalizer = $elementNormalizer;
    }
}
