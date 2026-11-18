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

namespace Pimcore\Bundle\GenericDataIndexBundle\Diagnostics;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Status\IndexQueueStatusService;
use Pimcore\Contracts\Diagnostics\CheckInterface;
use Pimcore\Contracts\Diagnostics\CheckResult;
use Pimcore\Contracts\Diagnostics\DataClass;
use Pimcore\Contracts\Diagnostics\DataContract;
use Pimcore\Contracts\Diagnostics\Finding;
use Pimcore\Contracts\Diagnostics\NotAvailable;
use Pimcore\Contracts\Diagnostics\Severity;
use Psr\Clock\ClockInterface;

/**
 * How many index queue entries wait and for how long.
 *
 * @internal
 */
#[DataContract(
    returns: [DataClass::Metric, DataClass::Configuration, DataClass::Identifier],
    neverReturns: ['element ids', 'queue payload', 'search hosts and credentials', 'exception messages'],
    sources: [
        'generic_data_index_queue (aggregates)',
        'search index configuration (client type, client name, prefix)',
    ],
    doc: 'doc/06_Diagnostics/README.md#index_queue_backlog',
)]
final readonly class IndexQueueBacklogCheck implements CheckInterface
{
    private const string ID = 'generic_data_index.index_queue_backlog';

    private const string SUBJECT = 'generic_data_index_queue';

    private const string BACKLOG_FINDING_CODE = 'index_queue_backlog';

    private const string NOT_CONSUMED_FINDING_CODE = 'index_queue_not_consumed';

    private const int WARNING_AGE_SECONDS = 3600;

    private const int BACKLOG_CRITICAL_AGE_SECONDS = 86400;

    /**
     * Stays well below the re-dispatch window (IndexQueueDispatchId::getRedispatchAfterSeconds(), 24 h): a stale
     * dispatched entry gets a fresh dispatch id then, so its age would never pass a one-day threshold.
     */
    private const int NOT_CONSUMED_CRITICAL_AGE_SECONDS = 21600;

    /**
     * Configuration names are reported only when they look like identifiers; anything else (e.g. a URL put into
     * the wrong option) is reported as null.
     */
    private const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9._\-]{0,255}$/D';

    public function __construct(
        private IndexQueueStatusService $statusService,
        private ClockInterface $clock,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function run(): CheckResult
    {
        try {
            if (!$this->statusService->isQueueTableAvailable()) {
                return new CheckResult(
                    metrics: $this->getConfigurationMetrics(),
                    notAvailable: [new NotAvailable(self::SUBJECT, 'table_missing')],
                );
            }

            $status = $this->statusService->getQueueStatus();
        } catch (Exception) {
            // The exception text may name hosts, users or queries, so only the fixed reason is reported.
            return new CheckResult(notAvailable: [new NotAvailable(self::SUBJECT, 'not_readable')]);
        }

        $oldestPendingAge = $this->getAgeInSeconds($status->getOldestPendingOperationTime());
        $oldestDispatchedAge = $this->getAgeInSeconds($status->getOldestDispatchTime());

        return new CheckResult(
            findings: [
                ...$this->getBacklogFindings($oldestPendingAge),
                ...$this->getNotConsumedFindings($oldestDispatchedAge),
            ],
            metrics: [
                'queue_total' => $status->getTotal(),
                'queue_dispatched' => $status->getDispatched(),
                'queue_pending' => $status->getPending(),
                'oldest_pending_age_seconds' => $oldestPendingAge,
                'oldest_dispatched_age_seconds' => $oldestDispatchedAge,
                ...$this->getConfigurationMetrics(),
            ],
        );
    }

    /**
     * @param int|null $millisecondTimestamp Unix timestamp in milliseconds
     */
    private function getAgeInSeconds(?int $millisecondTimestamp): ?int
    {
        if ($millisecondTimestamp === null) {
            return null;
        }

        return max(0, $this->clock->now()->getTimestamp() - intdiv($millisecondTimestamp, 1000));
    }

    /**
     * Pending entries wait to be dispatched to the messenger transport.
     *
     * @return list<Finding>
     */
    private function getBacklogFindings(?int $oldestPendingAge): array
    {
        return $this->getAgeFindings(
            $oldestPendingAge,
            self::BACKLOG_FINDING_CODE,
            self::BACKLOG_CRITICAL_AGE_SECONDS,
            'Index queue entries older than one hour are waiting.',
            'Index queue entries older than one day are waiting.',
        );
    }

    /**
     * Dispatched entries wait for a messenger worker. The age counts from the time the entry was dispatched (stored
     * in its dispatch id), not from the time it was queued, so a busy dispatcher does not look like a missing worker.
     * The dispatcher re-dispatches an entry after 24 h with a fresh dispatch id, so the critical level starts at 6 h.
     *
     * @return list<Finding>
     */
    private function getNotConsumedFindings(?int $oldestDispatchedAge): array
    {
        return $this->getAgeFindings(
            $oldestDispatchedAge,
            self::NOT_CONSUMED_FINDING_CODE,
            self::NOT_CONSUMED_CRITICAL_AGE_SECONDS,
            'Dispatched index queue entries older than one hour are not processed.',
            'Dispatched index queue entries older than six hours are not processed.',
        );
    }

    /**
     * @return list<Finding>
     */
    private function getAgeFindings(
        ?int $age,
        string $code,
        int $criticalAge,
        string $warningMessage,
        string $criticalMessage
    ): array {
        if ($age === null || $age <= self::WARNING_AGE_SECONDS) {
            return [];
        }

        if ($age > $criticalAge) {
            return [new Finding($code, Severity::Critical, $criticalMessage, self::SUBJECT)];
        }

        return [new Finding($code, Severity::Warning, $warningMessage, self::SUBJECT)];
    }

    /**
     * @return array{client_type: ?string, client_name: ?string, index_prefix: ?string}
     */
    private function getConfigurationMetrics(): array
    {
        $configuration = $this->statusService->getClientConfiguration();

        return [
            'client_type' => $this->asIdentifier($configuration->getClientType()),
            'client_name' => $this->asIdentifier($configuration->getClientName()),
            'index_prefix' => $this->asIdentifier($configuration->getIndexPrefix()),
        ];
    }

    private function asIdentifier(string $value): ?string
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $value) !== 1) {
            return null;
        }

        return $value;
    }
}
