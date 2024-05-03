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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Used by asset metadata fields to add the default language to the field name.
 *
 * @internal
 */
#[AsTaggedItem(priority: 9)]
final readonly class AssetMetadataDefaultLanguageTransformer implements FieldNameTransformerInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    ) {
    }

    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): ?string
    {
        // Relevant only for asset metadata fields
        if (!$this->mappingAnalyzerService->fieldPathExists(SystemField::FILE_SIZE->getPath(), $indexMapping)) {
            return null;
        }

        if (!$this->mappingAnalyzerService->fieldPathExists($fieldName, $indexMapping)) {
            return null;
        }


        $fullFieldName = $fieldName . '.' . MappingProperty::NOT_LOCALIZED_KEY;
        if ($this->mappingAnalyzerService->fieldPathExists($fullFieldName, $indexMapping)) {
            return $fullFieldName;
        }

        return null;
    }

    public function stopPropagation(): bool
    {
        return false;
    }
}
