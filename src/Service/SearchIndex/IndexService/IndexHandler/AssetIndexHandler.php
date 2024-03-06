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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\MetadataProviderServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class AssetIndexHandler extends AbstractIndexHandler
{
    private AssetTypeAdapter $assetAdapter;

    private MetadataProviderServiceInterface $metadataMappingProviderService;

    protected function extractMappingProperties(mixed $context = null): array
    {
        $mappingProperties = [
            FieldCategory::SYSTEM_FIELDS->value => [
                'properties' => $this->searchIndexConfigService
                    ->getSystemFieldsSettings(SearchIndexConfigService::SYSTEM_FIELD_ASSET),
            ],
            FieldCategory::STANDARD_FIELDS->value => ['properties' => []],
            FieldCategory::CUSTOM_FIELDS->value => [],
        ];

        foreach ($this->metadataMappingProviderService->getMappingProperties() as $mappingProperty) {
            $mappingProperties[FieldCategory::STANDARD_FIELDS->value]['properties'][$mappingProperty->getName()]
                = $mappingProperty->getMapping();
        }

        $mappingProperties[FieldCategory::CUSTOM_FIELDS->value]['properties'] =
            $this->fireEventAndGetCustomFieldsMapping($mappingProperties[FieldCategory::CUSTOM_FIELDS->value]);

        return $mappingProperties;
    }

    protected function getAliasIndexName(mixed $context = null): string
    {
        return $this->assetAdapter->getAliasIndexName($context);
    }

    #[Required]
    public function setAssetAdapter(AssetTypeAdapter $assetAdapter): void
    {
        $this->assetAdapter = $assetAdapter;
    }

    #[Required]
    public function setMetadataMappingProviderService(
        MetadataProviderServiceInterface $metadataMappingProviderService
    ): void
    {
        $this->metadataMappingProviderService = $metadataMappingProviderService;
    }

    private function fireEventAndGetCustomFieldsMapping($customFields): array
    {
        $extractMappingEvent = new ExtractMappingEvent($customFields);
        $this->eventDispatcher->dispatch($extractMappingEvent);

        return $extractMappingEvent->getCustomFieldsMapping();
    }
}
