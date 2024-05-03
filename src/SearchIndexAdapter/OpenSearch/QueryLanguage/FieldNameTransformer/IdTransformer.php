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

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Used for image and video data types to transform the field name to the id field.
 *
 * @internal
 */
#[AsTaggedItem(priority: 5)]
final readonly class IdTransformer implements FieldNameTransformerInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    ) {
    }

    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): ?string
    {
        if (!$this->mappingAnalyzerService->fieldPathExists($fieldName, $indexMapping)) {
            return null;
        }

        $fullFieldName = $fieldName . '.id';
        if ($this->mappingAnalyzerService->fieldPathExists($fullFieldName, $indexMapping)) {
            return $fullFieldName;
        }

        return null;
    }

    public function stopPropagation(): bool
    {
        return true;
    }
}
