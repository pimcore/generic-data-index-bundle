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

use Doctrine\DBAL\Connection;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;

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

    public function classDefinitionExists(string $name): bool
    {
        try {
            if ($this->connection->fetchOne('SELECT id FROM classes where name=?', [$name])) {
                return true;
            }
        } catch (Exception $e) {
            // do nothing
        }

        return false;
    }
}
