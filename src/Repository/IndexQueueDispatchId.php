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

namespace Pimcore\Bundle\GenericDataIndexBundle\Repository;

/**
 * Encoding of the index queue's "dispatched" column: the millisecond timestamp of the dispatch in the high-order
 * digits and a random uniqueness suffix in the low-order digits.
 *
 * @internal
 */
final class IndexQueueDispatchId
{
    /**
     * Factor reserving the low-order digits of a dispatch id for a random uniqueness suffix, while the millisecond
     * timestamp stays in the high-order digits.
     */
    private const int FACTOR = 1_000_000;

    /**
     * Dispatch ids written before the uniqueness suffix existed hold the plain millisecond timestamp. Any id below
     * this bound is read as such a plain timestamp, because an encoded id of a real dispatch time is far larger.
     */
    private const int MIN_ENCODED_ID = 1_000_000_000 * self::FACTOR;

    private function __construct()
    {
    }

    /**
     * Builds a dispatch id that stays unique even when several workers dispatch within the same millisecond.
     */
    public static function generate(int $millisecondTimestamp): int
    {
        return self::fromMillisecondTimestamp($millisecondTimestamp) + random_int(0, self::FACTOR - 1);
    }

    /**
     * The smallest dispatch id of the given millisecond timestamp, e.g. for a staleness threshold.
     */
    public static function fromMillisecondTimestamp(int $millisecondTimestamp): int
    {
        return $millisecondTimestamp * self::FACTOR;
    }

    /**
     * The millisecond timestamp at which the entry was dispatched.
     */
    public static function toMillisecondTimestamp(int $dispatchId): int
    {
        if ($dispatchId < self::MIN_ENCODED_ID) {
            return $dispatchId;
        }

        return intdiv($dispatchId, self::FACTOR);
    }
}
