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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Doctrine\DBAL\Connection;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final readonly class ElementService implements ElementServiceInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws InvalidElementTypeException
     */
    public function getElementByType(int $id, string $type): Asset|AbstractObject|Document|null
    {
        return match($type) {
            ElementType::ASSET->value => Asset::getById($id),
            ElementType::DATA_OBJECT->value => AbstractObject::getById($id),
            ElementType::DOCUMENT->value => Document::getById($id),
            default => throw new InvalidElementTypeException('Invalid element type: ' . $type)
        };
    }

    /**
     * @throws InvalidElementTypeException
     */
    public function getElementType(ElementInterface $element): ElementType
    {
        return match (true) {
            $element instanceof Asset => ElementType::ASSET,
            $element instanceof Document => ElementType::DOCUMENT,
            $element instanceof DataObject => ElementType::DATA_OBJECT,
            default => throw new InvalidElementTypeException('Invalid element type: ' . $element->getType())
        };
    }

    public function classDefinitionExists(string $name): bool
    {
        try {
            if ($this->connection->fetchOne('SELECT id FROM classes where name=?', [$name])) {
                return true;
            }
        } catch (Exception) {
            // do nothing
        }

        return false;
    }
}
