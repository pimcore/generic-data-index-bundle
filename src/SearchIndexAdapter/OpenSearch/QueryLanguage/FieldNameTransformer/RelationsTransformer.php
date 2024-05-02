<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @internal
 */
#[AsTaggedItem(index: 'relations_transformer', priority: 0)]
final readonly class RelationsTransformer implements FieldNameTransformerInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    )
    {
    }

    public function transformFieldName(string $fieldName, IndexEntity $indexEntity, array $indexMapping): ?string
    {
        if (!$this->mappingAnalyzerService->fieldPathExists($fieldName, $indexMapping)) {
            return null;
        }

        $addon = match($indexEntity->getIndexType()) {
            IndexType::DATA_OBJECT => 'object',
            IndexType::ASSET => 'asset',
            IndexType::DOCUMENT => 'document',
            default => null
        };

        if ($addon === null) {
            return null;
        }

        $fullFieldName = $fieldName . '.' . $addon;
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