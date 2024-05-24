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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\MetadataProviderServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class AssetIndexHandler extends AbstractIndexHandler
{
    public function __construct(
        SearchIndexServiceInterface $searchIndexService,
        SearchIndexConfigServiceInterface $searchIndexConfigService,
        EventDispatcherInterface $eventDispatcher,
        IndexMappingServiceInterface $indexMappingService,
        private readonly AssetTypeAdapter $assetAdapter,
        private readonly MetadataProviderServiceInterface $metadataProviderService,
        private readonly GlobalIndexAliasServiceInterface $globalIndexAliasService,
    )
    {
        parent::__construct($searchIndexService, $searchIndexConfigService, $eventDispatcher, $indexMappingService);
    }


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

        foreach ($this->metadataProviderService->getMappingProperties() as $mappingProperty) {
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

    protected function createGlobalIndexAliases(mixed $context = null): void
    {
        $currentIndexFullName = $this->getCurrentFullIndexName($context);
        $this->globalIndexAliasService->addToElementSearchAlias($currentIndexFullName);
    }

    private function fireEventAndGetCustomFieldsMapping($customFields): array
    {
        $extractMappingEvent = new ExtractMappingEvent($customFields);
        $this->eventDispatcher->dispatch($extractMappingEvent);

        return $extractMappingEvent->getCustomFieldsMapping();
    }
}
