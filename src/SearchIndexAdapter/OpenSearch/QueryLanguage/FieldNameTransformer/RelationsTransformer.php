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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Used by relation data types to transform the field name to the correct element type relation field.
 *
 * @internal
 */
#[AsTaggedItem(priority: 0)]
final readonly class RelationsTransformer implements FieldNameTransformerInterface
{
    public function __construct(
        private MappingAnalyzerServiceInterface $mappingAnalyzerService
    ) {
    }

    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): ?string
    {
        if ($targetEntity === null || !$this->mappingAnalyzerService->fieldPathExists($fieldName, $indexMapping)) {
            return null;
        }

        $addon = match($targetEntity->getIndexType()) {
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
