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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Status\IndexQueueStatusService;
use Pimcore\Contracts\Diagnostics\CheckInterface;
use Pimcore\Contracts\Diagnostics\CheckResult;
use Pimcore\Contracts\Diagnostics\DataClass;
use Pimcore\Contracts\Diagnostics\DataContract;
use Pimcore\Contracts\Diagnostics\Finding;
use Pimcore\Contracts\Diagnostics\NotAvailable;
use Pimcore\Contracts\Diagnostics\Severity;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * Health, document count and size of the search indices of the configured prefix.
 *
 * @internal
 */
#[DataContract(
    returns: [DataClass::Metric, DataClass::Infrastructure, DataClass::Identifier],
    neverReturns: ['document contents', 'search hosts and credentials', 'exception messages'],
    sources: ['search indices of the configured prefix'],
    doc: 'doc/06_Diagnostics/README.md#index_health',
)]
final readonly class IndexHealthCheck implements CheckInterface
{
    private const string ID = 'generic_data_index.index_health';

    private const string SUBJECT = 'indices';

    private const string INDEX_NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 ._+\-]{0,254}$/D';

    private const int MAX_INDEX_FINDINGS = 20;

    private const array HEALTH_VALUES = ['green', 'yellow', 'red'];

    public function __construct(
        private SearchClientInterface $client,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private IndexQueueStatusService $statusService,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function run(): CheckResult
    {
        try {
            $indices = $this->client->getAllIndices([
                'index' => $this->searchIndexConfigService->getIndexPrefix() . '*',
                'format' => 'json',
                'bytes' => 'b',
            ]);
        } catch (Exception) {
            // The exception text may name hosts or credentials, so only the fixed reason is reported.
            return $this->notReadable();
        }

        if (!array_is_list($indices)) {
            return $this->notReadable();
        }

        $metrics = [
            'index_count' => 0,
            'indices_red' => 0,
            'indices_yellow' => 0,
            'documents_total' => 0,
            'size_bytes_total' => 0,
        ];
        $findingsBySeverity = [Severity::Critical->value => [], Severity::Warning->value => []];

        foreach ($indices as $index) {
            if (!is_array($index) || !is_string($index['index'] ?? null) || $index['index'] === '') {
                return $this->notReadable();
            }

            $health = $index['health'] ?? null;
            $isClosed = ($index['status'] ?? null) === 'close';
            if (!$isClosed && !in_array($health, self::HEALTH_VALUES, true)) {
                return $this->notReadable();
            }

            $metrics['index_count']++;
            $metrics['documents_total'] += $this->toInt($index['docs.count'] ?? null);
            $metrics['size_bytes_total'] += $this->toInt($index['store.size'] ?? null);

            $severity = match ($health) {
                'red' => Severity::Critical,
                'yellow' => Severity::Warning,
                default => null,
            };
            if ($severity === null) {
                continue;
            }

            $metrics[$severity === Severity::Critical ? 'indices_red' : 'indices_yellow']++;
            $findingsBySeverity[$severity->value][] = new Finding(
                'index_not_green',
                $severity,
                'The index is not green.',
                $this->asIndexName($index['index']),
            );
        }

        // Critical findings first, so the cap never hides a red index behind yellow ones.
        $findings = array_slice(
            array_merge($findingsBySeverity[Severity::Critical->value], $findingsBySeverity[Severity::Warning->value]),
            0,
            self::MAX_INDEX_FINDINGS,
        );

        if ($metrics['index_count'] === 0 && $this->isQueueTableAvailable()) {
            $findings[] = new Finding(
                'no_indices_for_prefix',
                Severity::Warning,
                'No search index exists for the configured index prefix.',
            );
        }

        return new CheckResult(findings: $findings, metrics: $metrics);
    }

    private function notReadable(): CheckResult
    {
        return new CheckResult(notAvailable: [new NotAvailable(self::SUBJECT, 'not_readable')]);
    }

    private function isQueueTableAvailable(): bool
    {
        try {
            return $this->statusService->isQueueTableAvailable();
        } catch (Exception) {
            return false;
        }
    }

    private function asIndexName(mixed $name): ?string
    {
        if (!is_string($name) || preg_match(self::INDEX_NAME_PATTERN, $name) !== 1) {
            return null;
        }

        return $name;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }
}
