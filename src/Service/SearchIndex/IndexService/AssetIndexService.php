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

use DateTimeInterface;
use Exception;
use OpenSearch\Namespaces\IndicesNamespace;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

class AssetIndexService extends AbstractIndexService
{
    private const NOT_LOCALIZED_KEY = 'default';

    /**
     * @return IndicesNamespace
     */
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
        return $this->searchIndexConfigService->getIndexName('asset');
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

    protected function extractSystemFieldsMapping(): array
    {
        $mappingProperties = parent::extractSystemFieldsMapping();
        $mappingProperties[FieldCategory::SYSTEM_FIELDS->value]['properties'][SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value] = ['type' => 'boolean'];

        return $mappingProperties;
    }

    public function extractMapping(): array
    {
        $mappingProperties = $this->extractSystemFieldsMapping();
        $mappingProperties[FieldCategory::CUSTOM_FIELDS->value] = [];

        //$extractMappingEvent = new ExtractMappingEvent($mappingProperties[FieldCategory::CUSTOM_FIELDS->value]);
        //$this->eventDispatcher->dispatch($extractMappingEvent);
        //$mappingProperties[FieldCategory::CUSTOM_FIELDS->value]['properties'] =
        //  $extractMappingEvent->getCustomFieldsMapping();

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
     * @throws \JsonException
     */
    protected function doUpdateMapping(): AssetIndexService
    {
        $mapping = $this->extractMapping();
        $response = $this->getIndices()->putMapping($mapping);
        $this->logger->debug(json_encode($response, JSON_THROW_ON_ERROR));

        return $this;
    }

    /**
     * @param Asset $element
     * @throws \JsonException
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

        $checksum = crc32(json_encode([$systemFields, $standardFields, $customFields], JSON_THROW_ON_ERROR));
        $systemFields[SystemField::CHECKSUM->value] = $checksum;

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
            SystemField::ID->value => $asset->getId(),
            SystemField::CREATION_DATE->value => $date->setTimestamp(
                $asset->getCreationDate())->format(DateTimeInterface::ATOM
            ),
            SystemField::MODIFICATION_DATE->value => $date->setTimestamp(
                $asset->getModificationDate())->format(DateTimeInterface::ATOM
            ),
            SystemField::TYPE->value => $asset->getType(),
            SystemField::KEY->value => $asset->getKey(),
            SystemField::PATH->value => $asset->getPath(),
            SystemField::FULL_PATH->value => $asset->getRealFullPath(),
            SystemField::PATH_LEVELS->value => $this->extractPathLevels($asset),
            SystemField::TAGS->value => $this->extractTagIds($asset),
            SystemField::MIME_TYPE->value => $asset->getMimetype(),
            //SystemField::THUMBNAIL->value => $this->thumbnailService->getThumbnailPath(
            //  $asset,
            //  ImageThumbnails::ELEMENT_TEASER
            //),
            //SystemField::COLLECTIONS->value => $this->getCollectionIdsByElement($asset),
            //SystemField::PUBLIC_SHARES->value => $this->getPublicShareIdsByElement($asset),
            SystemField::USER_OWNER->value => $asset->getUserOwner(),
            SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value =>
                $this->workflowService->hasWorkflowWithPermissions($asset),
            SystemField::FILE_SIZE->value => $asset->getFileSize(),
        ];
    }

    private function getStandardFieldsIndexData(Asset $asset): array
    {
        $standardFields = [];

        foreach($asset->getMetadata() as $metadata) {
            if(is_array($metadata) && isset($metadata['data'], $metadata['name'])) {
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
     * Called in index.yaml
     */
    public function setCoreFieldsConfig(array $coreFieldsConfig): void
    {
        if (is_array($coreFieldsConfig['general']) && is_array($coreFieldsConfig['asset'])) {
            $this->coreFieldsConfig = array_merge($coreFieldsConfig['general'], $coreFieldsConfig['asset']);
        }
    }
}
