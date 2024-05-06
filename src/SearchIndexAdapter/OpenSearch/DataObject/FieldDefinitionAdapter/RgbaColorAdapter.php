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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;

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
