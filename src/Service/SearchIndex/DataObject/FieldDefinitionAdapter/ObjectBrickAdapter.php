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

use InvalidArgumentException;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\Objectbrick;

/**
 * @internal
 */
final class ObjectBrickAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
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
                $mapping[$adapter->getOpenSearchAttributeName()] = $adapter->getOpenSearchMapping();
            }
        }

        return $mapping;
    }
}
