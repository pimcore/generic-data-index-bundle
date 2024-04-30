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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @internal
 */
#[AsTaggedItem(index: 'field_category_transformer', priority: 0)]
final readonly class FieldCategoryTransformer implements FieldNameTransformerInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    ) {
    }

    public function transformFieldName(string $fieldName, array $indexMapping): ?string
    {
        if ($this->mappingAnalyzerService->fieldPathExists($fieldName, $indexMapping)) {
            return null;
        }

        $fieldCategories = [FieldCategory::STANDARD_FIELDS, FieldCategory::SYSTEM_FIELDS, FieldCategory::CUSTOM_FIELDS];
        foreach ($fieldCategories as $fieldCategory) {
            $prefixedFieldName = $fieldCategory->value . '.' . $fieldName;
            if ($this->mappingAnalyzerService->fieldPathExists($prefixedFieldName, $indexMapping)) {
                return $prefixedFieldName;
            }
        }

        return null;
    }

    public function stopPropagation(): bool
    {
        return false;
    }
}
