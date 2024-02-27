<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;

/**
 * @internal
 */
final class DateAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            'type' => AttributeType::DATE->value,
        ];
    }
}