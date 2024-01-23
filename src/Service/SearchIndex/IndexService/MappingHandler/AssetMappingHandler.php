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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\MappingHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;

class AssetMappingHandler extends AbstractMappingHandler
{
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

    protected function getIndexAliasName(mixed $context = null): string
    {
        return $this->searchIndexConfigService->getIndexName(ElementType::ASSET->value);
    }
}
