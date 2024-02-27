<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;

/**
 * @internal
 */
final class BooleanAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            'type' => AttributeType::BOOLEAN->value,
        ];
    }

    public function normalize(mixed $value): bool
    {
        return (bool) $value;
    }
}