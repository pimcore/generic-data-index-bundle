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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\RelationSummaries;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\RelationSummaryAdapterInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class RelationAdapter extends AbstractAdapter implements RelationSummaryAdapterInterface
{
    public function getIndexMapping(): array
    {
        return [
            'properties' => [
                'object' => [
                    'type' => AttributeType::LONG->value,
                ],
                'asset' => [
                    'type' => AttributeType::LONG->value,
                ],
                'document' => [
                    'type' => AttributeType::LONG->value,
                ],
            ],
        ];
    }

    public function normalize(mixed $value): ?array
    {
        if ($value instanceof RelationSummaries) {
            return $this->normalizeRelationSummaries($value);
        }

        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof NormalizerInterface) {
            return null;
        }

        $returnValue = [
            'object' => [],
            'asset' => [],
            'document' => [],
        ];
        $normalizedValues = $fieldDefinition->normalize($value);

        if (is_array($normalizedValues)) {
            // Mapping For ManyToOne
            if (isset($normalizedValues['type'], $normalizedValues['id'])) {
                $returnValue[$normalizedValues['type']][] = $normalizedValues['id'];
            }

            foreach ($normalizedValues as $normalizedValue) {
                if (isset($normalizedValue['type'], $normalizedValue['id'])) {
                    $returnValue[$normalizedValue['type']][] = $normalizedValue['id'];
                }
            }
        }

        return $returnValue;
    }

    /**
     * @throws Exception
     */
    public function getInheritedData(
        Concrete $dataObject,
        int $objectId,
        mixed $value,
        string $key,
        ?string $language = null,
        ?callable $callback = null
    ): array {
        if (!$value instanceof RelationSummaries) {
            return parent::getInheritedData($dataObject, $objectId, $value, $key, $language, $callback);
        }

        // the summaries already come from the nearest ancestor with relations
        return $value->getOwnerId() === $objectId ? [] : [$key => ['originId' => $value->getOwnerId()]];
    }

    private function normalizeRelationSummaries(RelationSummaries $relationSummaries): array
    {
        $returnValue = [
            'object' => [],
            'asset' => [],
            'document' => [],
        ];

        foreach ($relationSummaries->getSummaries() as $summary) {
            $returnValue[$summary['type']][] = $summary['id'];
        }

        return $returnValue;
    }
}
