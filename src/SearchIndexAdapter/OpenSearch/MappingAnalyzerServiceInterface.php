<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch;

/**
 * @internal
 */
interface MappingAnalyzerServiceInterface
{
    public function fieldPathExists(string $fieldPath, array $indexMappings): bool;
}