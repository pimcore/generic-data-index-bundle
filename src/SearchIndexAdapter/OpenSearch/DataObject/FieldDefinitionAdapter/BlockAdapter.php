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

use InvalidArgumentException;
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;

/**
 * @internal
 */
final class BlockAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Block) {
            throw new InvalidArgumentException('FieldDefinition must be an instance of ' . Block::class);
        }

        $items = $fieldDefinition->getFieldDefinitions();
        $properties = [];
        foreach ($items as $item) {
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($item);
            if ($adapter) {
                $properties[$item->getName()] = $adapter->getIndexMapping();
            }
        }

        return [
            'type' => 'nested',
            'properties' => $properties,
        ];
    }
}
