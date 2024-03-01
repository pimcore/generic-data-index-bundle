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

/**
 * @internal
 */
final class NumericRangeAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'properties' => [
                'maximum' => [
                    'type' => AttributeType::FLOAT->value,
                ],
                'minimum' => [
                    'type' => AttributeType::FLOAT->value,
                ],
            ],
        ];
    }
}