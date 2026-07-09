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

namespace Pimcore\Bundle\GenericDataIndexBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;

#[Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['dispatched'], name: self::TABLE . '_dispatched')]
#[ORM\Index(columns: ['operationTime'], name: self::TABLE . '_operation_time')]
#[ORM\UniqueConstraint(name: self::TABLE . '_element_id_type', columns: ['elementId', 'elementType'])]

/**
 * @internal
 */
class IndexQueue
{
    public const TABLE = 'generic_data_index_queue';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private int $id;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $elementId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $elementType;

    #[ORM\Column(type: 'string', length: 255)]
    private string $elementIndexName;

    #[ORM\Column(type: 'string', length: 20)]
    private string $operation;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $operationTime;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true, 'default' => 0])]
    private string $dispatched;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): IndexQueue
    {
        $this->id = $id;

        return $this;
    }

    public function getElementId(): int
    {
        return $this->elementId;
    }

    public function setElementId(int $elementId): IndexQueue
    {
        $this->elementId = $elementId;

        return $this;
    }

    public function getElementType(): string
    {
        return $this->elementType;
    }

    public function setElementType(string $elementType): IndexQueue
    {
        $this->elementType = $elementType;

        return $this;
    }

    public function getElementIndexName(): string
    {
        return $this->elementIndexName;
    }

    public function setElementIndexName(string $elementIndexName): IndexQueue
    {
        $this->elementIndexName = $elementIndexName;

        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): IndexQueue
    {
        $this->operation = $operation;

        return $this;
    }

    public function getOperationTime(): string
    {
        return $this->operationTime;
    }

    public function setOperationTime(string $operationTime): IndexQueue
    {
        $this->operationTime = $operationTime;

        return $this;
    }

    public function getDispatched(): string
    {
        return $this->dispatched;
    }

    public function setDispatched(string $dispatched): void
    {
        $this->dispatched = $dispatched;
    }
}
