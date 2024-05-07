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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\MappingProviderInterface;
use Pimcore\Model\Asset;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
final readonly class MetadataProviderService implements MetadataProviderServiceInterface
{
    public function __construct(
        private ServiceLocator $mappingProviderLocator,
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
