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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Status;

use InvalidArgumentException;

/**
 * Counts of the index queue at one point in time, as read by the index status reader for the diagnostic checks.
 * It holds counts and a timestamp only, no element ids or index data.
 *
 * @internal
 */
final readonly class QueueStatus
{
    /**
     * @param int $pending entries waiting to be dispatched to the messenger transport
     * @param int $dispatched entries handed to the messenger transport and not yet processed
     * @param int|null $oldestPendingOperationTime operation time (Unix timestamp in milliseconds) of the oldest
     *                                             pending entry; null when no entry is pending
     * @param int|null $oldestDispatchTime dispatch time (Unix timestamp in milliseconds) of the entry dispatched
     *                                     longest ago; null when no entry is dispatched
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private int $pending,
        private int $dispatched,
        private ?int $oldestPendingOperationTime,
        private ?int $oldestDispatchTime,
    ) {
        if ($pending < 0 || $dispatched < 0) {
            throw new InvalidArgumentException('Queue counts must not be negative.');
        }

        if (($pending === 0) !== ($oldestPendingOperationTime === null)) {
            throw new InvalidArgumentException(
                'The oldest pending operation time must be set exactly when entries are pending.'
            );
        }

        if (($dispatched === 0) !== ($oldestDispatchTime === null)) {
            throw new InvalidArgumentException(
                'The oldest dispatch time must be set exactly when entries are dispatched.'
            );
        }
    }

    public function getTotal(): int
    {
        return $this->pending + $this->dispatched;
    }

    public function getPending(): int
    {
        return $this->pending;
    }

    public function getDispatched(): int
    {
        return $this->dispatched;
    }

    public function getOldestPendingOperationTime(): ?int
    {
        return $this->oldestPendingOperationTime;
    }

    public function getOldestDispatchTime(): ?int
    {
        return $this->oldestDispatchTime;
    }
}
