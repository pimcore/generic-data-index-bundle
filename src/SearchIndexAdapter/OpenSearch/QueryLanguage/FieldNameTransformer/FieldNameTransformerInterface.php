<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer;

/**
 * @internal
 */
interface FieldNameTransformerInterface
{
    /**
     * Returns null if the transformer does not apply to the given field name.
     */
    public function transformFieldName(string $fieldName, array $indexMapping): ?string;

    /**
     * Stops the propagation of the field name transformation if the current transformer was applied.
     * If the transformation is stopped, the next transformer will not be called.
     */
    public function stopPropagation(): bool;
}