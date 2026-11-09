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
 * How many index queue entries wait and for how long. Registered only when the core diagnostics contract exists
 * (Pimcore 2026.4), see config/diagnostics.yaml.
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

    private const string FINDING_CODE = 'index_queue_backlog';

    private const int WARNING_AGE_SECONDS = 3600;

    private const int CRITICAL_AGE_SECONDS = 86400;

    /**
     * Configuration names are reported only when they look like identifiers; anything else (e.g. a URL put into
     * the wrong option) is reported as null.
     */
    private const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9._\-]{0,255}$/';

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

        return new CheckResult(
            findings: $this->getFindings($oldestPendingAge),
            metrics: [
                'queue_total' => $status->getTotal(),
                'queue_dispatched' => $status->getDispatched(),
                'queue_pending' => $status->getPending(),
                'oldest_pending_age_seconds' => $oldestPendingAge,
                ...$this->getConfigurationMetrics(),
            ],
        );
    }

    /**
     * @param int|null $operationTime Unix timestamp in milliseconds
     */
    private function getAgeInSeconds(?int $operationTime): ?int
    {
        if ($operationTime === null) {
            return null;
        }

        return max(0, $this->clock->now()->getTimestamp() - intdiv($operationTime, 1000));
    }

    /**
     * @return list<Finding>
     */
    private function getFindings(?int $oldestPendingAge): array
    {
        if ($oldestPendingAge === null || $oldestPendingAge <= self::WARNING_AGE_SECONDS) {
            return [];
        }

        if ($oldestPendingAge > self::CRITICAL_AGE_SECONDS) {
            return [new Finding(
                self::FINDING_CODE,
                Severity::Critical,
                'Index queue entries older than one day are waiting.',
                self::SUBJECT,
            )];
        }

        return [new Finding(
            self::FINDING_CODE,
            Severity::Warning,
            'Index queue entries older than one hour are waiting.',
            self::SUBJECT,
        )];
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
