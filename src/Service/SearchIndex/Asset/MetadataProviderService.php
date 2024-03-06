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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\MappingProviderInterface;
use Pimcore\Model\Asset;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
final class MetadataProviderService implements MetadataProviderServiceInterface
{
    public function __construct(
        private readonly ServiceLocator $mappingProviderLocator,
    ) {
    }

    public function getMappingProperties(): array
    {
        $mappingProperties = [];
        foreach ($this->getMappingProviders() as $mappingProvider) {
            foreach ($mappingProvider->getMappingProperties() as $mappingProperty) {
                $mappingProperties[$mappingProperty->getName()] ??= $mappingProperty;
            }
        }

        return $mappingProperties;
    }

    public function getSearchableMetaDataForAsset(Asset $asset): array
    {
        $result = [];

        $metaDataMap = $this->getMetaDataMap();
        foreach($asset->getMetadata() as $metadata) {
            if(is_array($metadata) && isset($metadata['data'], $metadata['name'], $metadata['type'])) {
                $mappingProperty = $metaDataMap[$metadata['name']] ?? null;
                $language = $metadata['language'] ?? null;
                $language = $language ?: MappingProperty::NOT_LOCALIZED_KEY;
                if ($mappingProperty
                    && $mappingProperty->getType() === $metadata['type']
                    && in_array($language, $mappingProperty->getLanguages(), true)
                ) {
                    $result[] = $metadata;
                }
            }
        }

        return $result;
    }

    /**
     * @return MappingProperty[]
     */
    private function getMetaDataMap(): array
    {
        $result = [];
        foreach ($this->getMappingProperties() as $mappingProperty) {
            $result[$mappingProperty->getName()] = $mappingProperty;
        }

        return $result;
    }

    /**
     * @return MappingProviderInterface[]
     */
    private function getMappingProviders(): array
    {
        $mappingProviders = [];
        foreach ($this->mappingProviderLocator->getProvidedServices() as $serviceType => $service) {
            $mappingProviders[] = $this->mappingProviderLocator->get($serviceType);
        }

        return $mappingProviders;
    }
}
