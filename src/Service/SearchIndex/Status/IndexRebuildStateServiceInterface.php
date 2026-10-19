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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * Remembers that a full rebuild of an index was queued, until its queue entries have been processed. The status
 * tells a queued rebuild from the normal lag of asynchronous indexing by this marker.
 *
 * @internal
 */
interface IndexRebuildStateServiceInterface
{
    /**
     * @throws Exception
     */
    public function markQueued(IndexStateTarget $target): void;

    /**
     * Milliseconds timestamp of the queued rebuild, null when none is pending.
     */
    public function getQueuedAt(IndexStateTarget $target): ?int;

    public function clear(IndexStateTarget $target): void;
}
