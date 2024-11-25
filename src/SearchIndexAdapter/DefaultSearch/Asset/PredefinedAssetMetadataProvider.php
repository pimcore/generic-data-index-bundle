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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\MappingProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Model\Metadata\Predefined;

/**
 * @internal
 */
final readonly class PredefinedAssetMetadataProvider implements MappingProviderInterface
{
    private const DEFAULT_METADATA = ['title', 'alt', 'copyright'];

    public function __construct(
        private LanguageServiceInterface $languageService,
        private FieldDefinitionServiceInterface $fieldDefinitionService
    ) {
    }

    public function getMappingProperties(): array
    {
        $mappingProperties = [];

        $predefinedMetaDataList = (new Predefined\Listing())->load();
        $languages = array_merge([MappingProperty::NOT_LOCALIZED_KEY], $this->languageService->getValidLanguages());

        foreach ($predefinedMetaDataList as $predefinedMetaData) {
            $mappingProperties[] = new MappingProperty(
                $predefinedMetaData->getName(),
                $predefinedMetaData->getType(),
                $this->getLanguageMappingByType($languages, $predefinedMetaData->getType()),
                $languages
            );
        }

        return array_merge($mappingProperties, $this->getDefaultMetadataMapping($languages));
    }

    private function getTypeMapping(string $type): ?array
    {
        return $this->fieldDefinitionService
            ->getFieldDefinitionAdapter($type)
            ?->getIndexMapping();
    }

    private function getLanguageMappingByType(array $languages, string $type): array
    {
        $languageMapping = [
            'properties' => [],
        ];

        if ($typeMapping = $this->getTypeMapping($type)) {
            foreach ($languages as $language) {
                $languageMapping['properties'][$language] = $typeMapping;
            }
        }

        return $languageMapping;
    }

    /**
     * @return MappingProperty[]
     */
    private function getDefaultMetadataMapping(array $languages): array
    {
        $mappingProperties = [];
        foreach (self::DEFAULT_METADATA as $metadata) {
            $mappingProperties[] = new MappingProperty(
                $metadata,
                'input',
                $this->getLanguageMappingByType($languages, 'input'),
                $languages
            );
        }

        return $mappingProperties;
    }
}
