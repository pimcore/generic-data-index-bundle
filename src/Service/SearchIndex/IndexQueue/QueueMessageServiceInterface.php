<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

/**
 * @internal
 */
interface QueueMessageServiceInterface
{
    public function handleMessage(
        int $entriesCount,
        int $maxBatchSize
    ): void;

    public function getMaxBatchSize(
        int $entriesCount,
        int $workerCount,
        int $minBatchSize,
        int $maxBatchSize
    ): int;
}
