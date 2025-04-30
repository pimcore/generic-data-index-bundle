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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameTransformer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameTransformerInterface;

/**
 * Used to filter based on a keyword subfield if available.
 *
 * @internal
 */
final readonly class SynonymTransformer implements FieldNameTransformerInterface
{
    private const SYNONYM_FIELDS = [
        'fullpath' => 'fullPath',
    ];

    private const SYNONYM_FIELDS_ASSET = [
        'filename' => 'key',
    ];

    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): ?string
    {
        $synonymFields = self::SYNONYM_FIELDS;
        if ($targetEntity && $targetEntity->getIndexType() === IndexType::ASSET) {
            $synonymFields = array_merge($synonymFields, self::SYNONYM_FIELDS_ASSET);
        }

        return $synonymFields[$fieldName] ?? null;
    }

    public function stopPropagation(): bool
    {
        return false;
    }
}
