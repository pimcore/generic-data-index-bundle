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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status;

use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * Dispatches IndexCompleteEvent once, when the last queue entries of a queued full rebuild have been processed.
 * A failed check is logged and never fails the caller; the rebuild marker then stays, and the next batch of the
 * index checks again.
 *
 * @internal
 */
interface IndexCompletionNotifierInterface
{
    /**
     * Called after a batch of index queue entries was processed and removed from the queue.
     *
     * @param IndexQueue[] $entries
     */
    public function afterBatch(array $entries): void;

    /**
     * Called once every element of a full rebuild is in the queue: the workers may have processed them all
     * while they were still being queued, or the index has no elements.
     */
    public function afterEnqueue(IndexStateTarget $target): void;
}
