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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Closure;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;

/**
 * @internal
 */
final class QueueGate
{
    private readonly Closure $sleep;

    public function __construct(
        private readonly IndexStatsServiceInterface $indexStatsService,
        ?Closure $sleep = null,
        private readonly int $pollIntervalSeconds = 5,
    ) {
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    public function count(): int
    {
        return $this->indexStatsService->getStats()->getCountIndexQueueEntries();
    }

    public function await(?int $maxQueueEntries, int $waitSeconds): int
    {
        $count = $this->count();
        if ($maxQueueEntries === null) {
            return $count;
        }
        $waited = 0;
        while ($count > $maxQueueEntries && $waited < $waitSeconds) {
            ($this->sleep)($this->pollIntervalSeconds);
            $waited += $this->pollIntervalSeconds;
            $count = $this->count();
        }
        if ($count > $maxQueueEntries) {
            throw new SnapshotExportException(sprintf(
                'Index queue holds %d entries, more than the allowed %d. Wait for the consumers or raise --max-queue-entries.',
                $count,
                $maxQueueEntries
            ));
        }

        return $count;
    }
}
