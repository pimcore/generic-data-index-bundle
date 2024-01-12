<?php

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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\PortalEngineBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

class AssetIndexService extends AbstractIndexService
{
    const NOT_LOCALIZED_KEY = 'default';

    protected function getIndexName(ElementInterface $element): string
    {
        return $this->getAssetIndexName();
    }

    protected function getAssetIndexName(): string
    {
        return $this->searchIndexConfigService->getIndexName('asset');
    }

    protected function getCurrentFullIndexName(): string
    {
        $indexName = $this->getAssetIndexName();
        $currentIndexVersion = $this->openSearchService->getCurrentIndexVersion($indexName);

        return $indexName . '-' . ($currentIndexVersion === 'even' ? 'even' : 'odd');
    }

    public function createIndex(): self
    {
        $fullIndexName = $this->getCurrentFullIndexName();

        $this->openSearchService
            ->createIndex($fullIndexName)
            ->addAlias($fullIndexName, $this->getAssetIndexName());

        return $this;
    }

    public function deleteIndex(): self
    {
        $this
            ->openSearchService
            ->deleteIndex($this->getCurrentFullIndexName())
            ->deleteIndex($this->getAssetIndexName());

        return $this;
    }

    protected function extractSystemFieldsMapping(): array
    {
        $mappingProperties = parent::extractSystemFieldsMapping();
        $mappingProperties[FieldCategory::SYSTEM_FIELDS->value]['properties'][FieldCategory\SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value] = ['type' => 'boolean'];

        return $mappingProperties;
    }

    public function extractMapping(): array
    {
        $mappingProperties = $this->extractSystemFieldsMapping();
        $mappingProperties[FieldCategory::CUSTOM_FIELDS->value] = [];

        //$extractMappingEvent = new ExtractMappingEvent($mappingProperties[FieldCategory::CUSTOM_FIELDS->value]);
        //$this->eventDispatcher->dispatch($extractMappingEvent);
        //$mappingProperties[FieldCategory::CUSTOM_FIELDS->value]['properties'] = $extractMappingEvent->getCustomFieldsMapping();

        $mappingParams = [
            'index' => $this->getAssetIndexName(),
            'body' => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $mappingProperties,
            ],
        ];

        return $mappingParams;
    }

    /**
     * @param bool $forceCreateIndex
     *
     * @return $this
     */
    public function updateMapping($forceCreateIndex = false)
    {

        if ($forceCreateIndex || !$this->openSearchClient->indices()->existsAlias(['name' => $this->getAssetIndexName()])) {
            $this->createIndex();
        }

        try {
            $this->doUpdateMapping();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->openSearchService->reindex($this->getAssetIndexName(), $this->extractMapping());
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function doUpdateMapping()
    {
        $mapping = $this->extractMapping();
        $response = $this->openSearchClient->indices()->putMapping($mapping);
        $this->logger->debug(json_encode($response));

        return $this;
    }

    /**
     * @param Asset $element
     *
     * @return array
     */
    protected function getIndexData(ElementInterface $element): array
    {
        $systemFields = $this->getSystemFieldsIndexData($element);
        $standardFields = $this->getStandardFieldsIndexData($element);
        $customFields = [];

        //dispatch event before building checksum
        //$updateIndexDataEvent = new UpdateIndexDataEvent($asset, $customFields);
        //$this->eventDispatcher->dispatch($updateIndexDataEvent);
        //$customFields = $updateIndexDataEvent->getCustomFields();

        $checksum = crc32(json_encode([$systemFields, $standardFields, $customFields]));
        $systemFields[FieldCategory\SystemField::CHECKSUM->value] = $checksum;

        return [
            FieldCategory::SYSTEM_FIELDS->value => $systemFields,
            FieldCategory::STANDARD_FIELDS->value => $standardFields,
            FieldCategory::CUSTOM_FIELDS->value => $customFields,
        ];
    }

    /**
     * returns system fields index data array for given $asset
     */
    private function getSystemFieldsIndexData(Asset $asset): array
    {
        $date = new \DateTime();

        return [
            FieldCategory\SystemField::ID->value => $asset->getId(),
            FieldCategory\SystemField::CREATION_DATE->value => $date->setTimestamp($asset->getCreationDate())->format(\DateTimeInterface::ATOM),
            FieldCategory\SystemField::MODIFICATION_DATE->value => $date->setTimestamp($asset->getModificationDate())->format(\DateTimeInterface::ATOM),
            FieldCategory\SystemField::TYPE->value => $asset->getType(),
            FieldCategory\SystemField::KEY->value => $asset->getKey(),
            FieldCategory\SystemField::PATH->value => $asset->getPath(),
            FieldCategory\SystemField::FULL_PATH->value => $asset->getRealFullPath(),
            FieldCategory\SystemField::PATH_LEVELS->value => $this->extractPathLevels($asset),
            FieldCategory\SystemField::TAGS->value => $this->extractTagIds($asset),
            FieldCategory\SystemField::MIME_TYPE->value => $asset->getMimetype(),
            //FieldCategory\SystemField::THUMBNAIL->value => $this->thumbnailService->getThumbnailPath($asset, ImageThumbnails::ELEMENT_TEASER),
            //FieldCategory\SystemField::COLLECTIONS->value => $this->getCollectionIdsByElement($asset),
            //FieldCategory\SystemField::PUBLIC_SHARES->value => $this->getPublicShareIdsByElement($asset),
            FieldCategory\SystemField::USER_OWNER->value => $asset->getUserOwner(),
            FieldCategory\SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value => $this->workflowService->hasWorkflowWithPermissions($asset),
            FieldCategory\SystemField::FILE_SIZE->value => $asset->getFileSize(),
        ];
    }

    private function getStandardFieldsIndexData(Asset $asset): array
    {
        $standardFields = [];

        foreach($asset->getMetadata() as $metadata) {
            if(is_array($metadata) && isset($metadata['data']) && isset($metadata['name'])) {
                $data = $metadata['data'];
                $language = $metadata['language'] ?? null;
                $language = $language ?: self::NOT_LOCALIZED_KEY;
                $standardFields[$language] = $standardFields[$language] ?? [];
                $standardFields[$language][$metadata['name']] = $this->transformMetadataForIndex($data);
            }
        }

        return $standardFields;
    }

    private function transformMetadataForIndex(mixed $data): mixed
    {
        if($data instanceof ElementInterface) {
            return [
                'type' => Service::getElementType($data),
                'id' => $data->getId(),
            ];
        }

        return $data;
    }

    /**
     * Called in index.yml
     *
     * @param array $coreFieldsConfig
     */
    public function setCoreFieldsConfig(array $coreFieldsConfig)
    {
        if (is_array($coreFieldsConfig['general']) && is_array($coreFieldsConfig['asset'])) {
            $this->coreFieldsConfig = array_merge($coreFieldsConfig['general'], $coreFieldsConfig['asset']);
        }
    }
}
