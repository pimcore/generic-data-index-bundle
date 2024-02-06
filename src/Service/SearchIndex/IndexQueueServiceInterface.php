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
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface IndexQueueServiceInterface
{
    public function updateIndexQueue(
        ElementInterface $element,
        string $operation,
        bool $processSynchronously = false
    ): IndexQueueService;

    /**
     * @param IndexQueue[] $entries
     */
    public function handleIndexQueueEntries(array $entries): void;

    public function commit(?string $refreshIndex = null): IndexQueueService;
}
