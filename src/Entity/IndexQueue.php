<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]

class IndexQueue
{
    const TABLE = 'generic_data_index_queue';

    #[ORM\Id()]
    #[ORM\Column(type: "integer")]
    private int $elementId;

    #[ORM\Id()]
    #[ORM\Column(type: "string", length: 20)]
    private string $elementType;

    #[ORM\Column(type: "string", length: 10)]
    private string $elementIndexName;

    #[ORM\Column(type: "string", length: 20)]
    private string $operation;

    #[ORM\Column(type: "integer")]
    private int $operationTime;

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

    public function getOperationTime(): int
    {
        return $this->operationTime;
    }

    public function setOperationTime(int $operationTime): IndexQueue
    {
        $this->operationTime = $operationTime;
        return $this;
    }

}
