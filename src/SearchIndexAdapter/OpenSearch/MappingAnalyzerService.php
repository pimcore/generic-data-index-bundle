<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch;

/**
 * @internal
 */
final readonly class MappingAnalyzerService implements MappingAnalyzerServiceInterface
{
    public function fieldPathExists(string $fieldPath, array $indexMappings): bool
    {
        foreach ($indexMappings as $indexMapping) {

            if ($this->fieldPathExistsInMapping($fieldPath, $indexMapping['mappings'])) {
                return true;
            }
        }

        return false;
    }

    private function fieldPathExistsInMapping(string $fieldPath, array $mapping): bool
    {
        $fieldPathParts = explode('.', $fieldPath, 2);
        $field = $fieldPathParts[0];
        $subField = $fieldPathParts[1] ?? null;

        if (array_key_exists($field, $mapping['properties'])) {
            return empty($subField) || $this->fieldPathExistsInMapping($subField, $mapping['properties'][$field]);
        }

        return false;
    }
}