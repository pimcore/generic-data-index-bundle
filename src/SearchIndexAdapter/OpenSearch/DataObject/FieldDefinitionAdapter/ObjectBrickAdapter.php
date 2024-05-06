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
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\Objectbrick;

/**
 * @internal
 */
final class ObjectBrickAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        $objectBricks = $this->getFieldDefinition();
        $mapping = [];
        if (!$objectBricks instanceof Objectbricks) {
            throw new InvalidArgumentException(
                'FieldDefinition must be of type Data\Objectbricks'
            );
        }

        foreach ($objectBricks->getAllowedTypes() as $type) {
            $mapping[$type]['properties'] = $this->getMappingForObjectBrick($type);
        }

        return [
            'properties' => $mapping,
        ];
    }

    private function getMappingForObjectBrick(string $objectBrickType): array
    {
        $fieldDefinitions = Objectbrick\Definition::getByKey($objectBrickType)?->getFieldDefinitions();
        $mapping = [];
        foreach ($fieldDefinitions as $fieldDefinition) {
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($fieldDefinition);
            if ($adapter) {
                $mapping[$adapter->getIndexAttributeName()] = $adapter->getIndexMapping();
            }
        }

        return $mapping;
    }
}
