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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status;

use InvalidArgumentException;

/**
 * The status of all indices plus the figures that are not per index.
 */
final readonly class IndexStatusReport
{
    /**
     * @param IndexStatus[] $indices
     * @param int $queueEntries all index queue entries
     * @param int|null $failedMessages messages in the failed transport, null when it cannot be counted
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private array $indices,
        private int $queueEntries,
        private ?int $failedMessages,
    ) {
        if ($queueEntries < 0 || ($failedMessages !== null && $failedMessages < 0)) {
            throw new InvalidArgumentException('Counts of a status report cannot be negative.');
        }
    }

    /**
     * @return IndexStatus[]
     */
    public function getIndices(): array
    {
        return $this->indices;
    }

    /**
     * @return IndexStatus[]
     */
    public function getIncompleteIndices(): array
    {
        return array_values(array_filter(
            $this->indices,
            static fn (IndexStatus $status): bool => !$status->isComplete()
        ));
    }

    public function isComplete(): bool
    {
        return $this->getIncompleteIndices() === [];
    }

    public function getQueueEntries(): int
    {
        return $this->queueEntries;
    }

    public function getFailedMessages(): ?int
    {
        return $this->failedMessages;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'complete' => $this->isComplete(),
            'queueEntries' => $this->queueEntries,
            'failedMessages' => $this->failedMessages,
            'indices' => array_map(static fn (IndexStatus $status): array => $status->toArray(), $this->indices),
        ];
    }
}
