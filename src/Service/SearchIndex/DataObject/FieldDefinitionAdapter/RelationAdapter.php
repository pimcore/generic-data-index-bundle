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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class RelationAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
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

    public function normalize(mixed $value): mixed
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof NormalizerInterface) {
            return null;
        }

        $normalizedValues = $fieldDefinition->normalize($value);
        $returnValue = [
            'object' => [],
            'asset' => [],
            'document' => [],
        ];

        // Mapping For ManyToOne
        if (isset($normalizedValues['type'], $normalizedValues['id'])) {
            $returnValue[$normalizedValues['type']][] = $normalizedValues['id'];
        }

        foreach ($normalizedValues as $normalizedValue) {
            if (isset($normalizedValue['type'], $normalizedValue['id'])) {
                $returnValue[$normalizedValue['type']][] = $normalizedValue['id'];
            }
        }

        return $returnValue;
    }
}
