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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;

/**
 * @internal
 */
final class ElementService implements ElementServiceInterface
{
    /**
     * @throws InvalidElementTypeException
     */
    public function getElementByType(int $id, string $type): Asset|AbstractObject|null
    {
        return match($type) {
            ElementType::ASSET->value => Asset::getById($id),
            ElementType::DATA_OBJECT->value => AbstractObject::getById($id),
            default => throw new InvalidElementTypeException('Invalid element type: ' . $type)
        };
    }
}
