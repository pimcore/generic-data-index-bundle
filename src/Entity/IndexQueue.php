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

namespace Pimcore\Bundle\GenericDataIndexBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;

#[Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['dispatched'], name: self::TABLE . '_dispatched')]
#[ORM\Index(columns: ['operationTime'], name: self::TABLE . '_operation_time')]

/**
 * @internal
 */
class IndexQueue
{
    public const TABLE = 'generic_data_index_queue';

    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $elementId;

    #[ORM\Id]
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
