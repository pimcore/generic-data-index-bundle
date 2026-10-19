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
use JsonException;

/**
 * The marker of a queued full rebuild: when it was queued and whether every element of it is in the index queue
 * yet. While the elements are still being queued, an empty queue says nothing about the rebuild.
 *
 * @internal
 */
final readonly class IndexRebuildMarker
{
    /**
     * @param int $queuedAt milliseconds timestamp of the rebuild
     * @param bool $enqueued every element of the rebuild is in the index queue
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private int $queuedAt,
        private bool $enqueued,
    ) {
        if ($queuedAt < 0) {
            throw new InvalidArgumentException('A rebuild marker needs a timestamp.');
        }
    }

    /**
     * Null for a value that is no marker (e.g. written by another version).
     */
    public static function fromStoredValue(string $value): ?self
    {
        try {
            $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($data) || !is_int($data['queuedAt'] ?? null) || !is_bool($data['enqueued'] ?? null)) {
            return null;
        }

        return new self($data['queuedAt'], $data['enqueued']);
    }

    public function toStoredValue(): string
    {
        return json_encode(['queuedAt' => $this->queuedAt, 'enqueued' => $this->enqueued], JSON_THROW_ON_ERROR);
    }

    public function withEnqueued(): self
    {
        return new self($this->queuedAt, true);
    }

    public function getQueuedAt(): int
    {
        return $this->queuedAt;
    }

    public function isEnqueued(): bool
    {
        return $this->enqueued;
    }
}
