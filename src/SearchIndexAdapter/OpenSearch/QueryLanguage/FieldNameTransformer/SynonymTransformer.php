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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;

/**
 * Used to filter based on a keyword subfield if available.
 *
 * @internal
 */
final readonly class SynonymTransformer implements FieldNameTransformerInterface
{
    private const SYNONYM_FIELDS = [
        'fullPath' => 'fullpath',
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
