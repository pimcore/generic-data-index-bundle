<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

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

        if (array_key_exists($field, $mapping['properties'] ?? [])) {
            return empty($subField) || $this->fieldPathExistsInMapping($subField, $mapping['properties'][$field]);
        }

        if (array_key_exists($field, $mapping['fields'] ?? [])) {
            return empty($subField) || $this->fieldPathExistsInMapping($subField, $mapping['fields'][$field]);
        }

        return false;
    }
}
