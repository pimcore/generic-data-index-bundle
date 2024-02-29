<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

/**
 * @internal
 */
final class RelationAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'properties' => [
                'id' => [
                    'type' => AttributeType::LONG,
                ],
                'type' => [
                    'type' => AttributeType::TEXT,
                ],
            ],
        ];
    }

    public function normalize(mixed $value): mixed
    {
        if($value instanceof ElementInterface) {
            return [
                'type' => Service::getElementType($value),
                'id' => $value->getId(),
            ];
        }

        return null;
    }


}