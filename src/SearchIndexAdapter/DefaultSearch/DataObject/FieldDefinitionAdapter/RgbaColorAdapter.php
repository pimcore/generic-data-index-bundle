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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;

/**
 * @internal
 */
final class RgbaColorAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'properties' => [
                'r' => [
                    'type' => AttributeType::INTEGER->value,
                ],
                'g' => [
                    'type' => AttributeType::INTEGER->value,
                ],
                'b' => [
                    'type' => AttributeType::INTEGER->value,
                ],
                'a' => [
                    'type' => AttributeType::INTEGER->value,
                ],
            ],
        ];
    }
}
