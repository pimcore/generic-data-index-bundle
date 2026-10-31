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
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexRebuildMarker;

/**
 * Remembers that a full rebuild of an index was queued, until its queue entries have been processed. The status
 * tells a queued rebuild from the normal lag of asynchronous indexing by this marker.
 *
 * The marker is set before the first element is queued and advanced once every element is; it is removed by
 * exactly one caller, so that a rebuild is reported complete once, by the worker or the enqueue that claims it.
 *
 * @internal
 */
interface IndexRebuildStateServiceInterface
{
    /**
     * Marks that the elements of a full rebuild are being queued; replaces the marker of an earlier rebuild.
     *
     * @throws Exception
     */
    public function markEnqueuing(IndexStateTarget $target): IndexRebuildMarker;

    /**
     * Marks that every element of the rebuild is in the queue. False when the marker was replaced by a newer
     * rebuild meanwhile; that rebuild is then reported on its own.
     *
     * @throws Exception
     */
    public function markEnqueued(IndexStateTarget $target, IndexRebuildMarker $marker): bool;

    public function getMarker(IndexStateTarget $target): ?IndexRebuildMarker;

    /**
     * Removes exactly this marker. False when it is gone already (another worker completed the rebuild) or was
     * replaced by a newer rebuild.
     *
     * @throws Exception
     */
    public function claim(IndexStateTarget $target, IndexRebuildMarker $marker): bool;
}
