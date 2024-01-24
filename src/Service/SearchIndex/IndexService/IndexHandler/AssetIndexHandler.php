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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Symfony\Contracts\Service\Attribute\Required;

class AssetIndexHandler extends AbstractIndexHandler
{
    private AssetTypeAdapter $assetAdapter;

    protected function extractMappingProperties(mixed $context = null): array
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
}
