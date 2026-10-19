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

/**
 * Dispatches IndexCompleteEvent when the last queue entries of a queued full rebuild have been processed.
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
}
