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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;

/**
 * Prepends the field category (system_fields, standard_fields, custom_fields)
 * to the field name if it is not already present.
 *
 * @internal
 */
final readonly class FieldCategoryTransformer implements FieldNameTransformerInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    ) {
    }

    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): ?string
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
