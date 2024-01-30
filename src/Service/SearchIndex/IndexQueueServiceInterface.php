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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface IndexQueueServiceInterface
{
    public function updateIndexQueue(
        ElementInterface $element,
        string $operation,
        bool $doIndexElement = false
    ): IndexQueueService;

    /**
     * @param IndexQueue[] $entries
     *
     */
    public function handleIndexQueueEntries(array $entries): IndexQueueService;

    /**
     * @throws InvalidElementTypeException
     */
    public function getElement(int $id, string $type): Asset|AbstractObject|null;

    public function isPerformIndexRefresh(): bool;

    public function setPerformIndexRefresh(bool $performIndexRefresh): IndexQueueService;

    public function dispatchQueueMessages(bool $synchronously = false): void;

    public function commit(): IndexQueueService;
}
