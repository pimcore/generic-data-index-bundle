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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\MappingProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Model\Metadata\Predefined;

/**
 * @internal
 */
final class PredefinedAssetMetadataProvider implements MappingProviderInterface
{
    public function __construct(
        private readonly LanguageServiceInterface $languageService,
        private readonly FieldDefinitionServiceInterface $fieldDefinitionService
    ) {
    }

    public function getMappingProperties(): array
    {
        $mappingProperties = [];

        $predefinedMetaDataList = (new Predefined\Listing())->load();
        $languages = array_merge([MappingProperty::NOT_LOCALIZED_KEY], $this->languageService->getValidLanguages());

        foreach ($predefinedMetaDataList as $predefinedMetaData) {
            $languageMapping = [
                'properties' => [],
            ];

            if ($typeMapping = $this->getTypeMapping($predefinedMetaData->getType())) {
                foreach ($languages as $language) {
                    $languageMapping['properties'][$language] = $typeMapping;
                }
            }

            $mappingProperties[] = new MappingProperty(
                $predefinedMetaData->getName(),
                $predefinedMetaData->getType(),
                $languageMapping,
                $languages
            );
        }

        return $mappingProperties;
    }

    private function getTypeMapping(string $type): ?array
    {
        return $this->fieldDefinitionService
            ->getFieldDefinitionAdapter($type)
            ?->getIndexMapping();
    }
}
