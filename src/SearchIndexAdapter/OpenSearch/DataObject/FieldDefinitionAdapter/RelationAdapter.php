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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class RelationAdapter extends AbstractAdapter
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
}
