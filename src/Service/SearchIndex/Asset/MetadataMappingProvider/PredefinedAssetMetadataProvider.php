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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\MetadataMappingProvider;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\AssetNormalizer;
use Pimcore\Model\MetaData\Predefined;

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

    public function addMapping(array $mapping): array
    {
        $mapping['properties'] = $mapping['properties'] ?? [];

        $predefinedMetadata = (new Predefined\Listing())->load();
        $languages = array_merge([AssetNormalizer::NOT_LOCALIZED_KEY], $this->languageService->getValidLanguages());

        foreach ($predefinedMetadata as $predefinedMetaData) {
            $languageMapping = [
                'properties' => [],
            ];

            if ($typeMapping = $this->getTypeMapping($predefinedMetaData->getType())) {
                foreach ($languages as $language) {
                    $languageMapping['properties'][$language] = $typeMapping;
                }
            }

            $mapping['properties'][$predefinedMetaData->getType()] = $languageMapping;
        }

        return $mapping;
    }

    private function getTypeMapping(string $type): mixed
    {
        return $this->fieldDefinitionService
            ->getFieldDefinitionAdapter($type)
            ?->getIndexMapping();
    }
}
