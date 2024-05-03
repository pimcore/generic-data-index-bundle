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
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Prepends the field category (system_fields, standard_fields, custom_fields)
 * to the field name if it is not already present.
 *
 * @internal
 */
#[AsTaggedItem(priority: 10)]
final readonly class FieldCategoryTransformer implements FieldNameTransformerInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    ) {
    }

    public function transformFieldName(string $fieldName, IndexEntity $indexEntity, array $indexMapping): ?string
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
