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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\StandardField\Document\DocumentStandardField;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class DocumentIndexHandler extends AbstractIndexHandler
{
    private DocumentTypeAdapter $documentAdapter;

    protected function extractMappingProperties(mixed $context = null): array
    {
        $mappingProperties = [
            FieldCategory::SYSTEM_FIELDS->value => [
                'properties' => $this->searchIndexConfigService
                    ->getSystemFieldsSettings(SearchIndexConfigService::SYSTEM_FIELD_DOCUMENT),
            ],
            FieldCategory::STANDARD_FIELDS->value => $this->getMappingForStandardFields(),
            FieldCategory::CUSTOM_FIELDS->value => [],
        ];

        $mappingProperties[FieldCategory::CUSTOM_FIELDS->value]['properties'] =
            $this->fireEventAndGetCustomFieldsMapping($mappingProperties[FieldCategory::CUSTOM_FIELDS->value]);

        return $mappingProperties;
    }

    protected function getAliasIndexName(mixed $context = null): string
    {
        return $this->documentAdapter->getAliasIndexName($context);
    }

    #[Required]
    public function setDocumentAdapter(DocumentTypeAdapter $documentAdapter): void
    {
        $this->documentAdapter = $documentAdapter;
    }

    private function getMappingForStandardFields(): array
    {
        $attributes = $this->searchIndexConfigService->getSearchAnalyzerAttributes();
        $keywordMapping = $this->indexMappingService->getMappingForTextKeyword($attributes);

        return [
            'properties' => [
                DocumentStandardField::NAVIGATION_NAME->value => $keywordMapping,
                DocumentStandardField::NAVIGATION_TITLE->value => $keywordMapping,
            ]
        ];
    }

    private function fireEventAndGetCustomFieldsMapping($customFields): array
    {
        $extractMappingEvent = new ExtractMappingEvent($customFields);
        $this->eventDispatcher->dispatch($extractMappingEvent);

        return $extractMappingEvent->getCustomFieldsMapping();
    }
}
