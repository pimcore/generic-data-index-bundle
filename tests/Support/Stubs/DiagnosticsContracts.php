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

/**
 * Static analysis stubs of the core diagnostics contract (Pimcore\Contracts\Diagnostics, ships with Pimcore 2026.4).
 * phpstan-bootstrap.php loads them only when the contract is not installed, so src/Diagnostics is analysed on every
 * core line. Never loaded at runtime: without the contract the checks are not registered.
 */

namespace Pimcore\Contracts\Diagnostics;

use Attribute;

interface CheckInterface
{
    public const string TAG = 'pimcore.diagnostics.check';

    public function getId(): string;

    public function run(): CheckResult;
}

enum DataClass: string
{
    case Version = 'version';
    case Configuration = 'configuration';
    case Infrastructure = 'infrastructure';
    case Metric = 'metric';
    case Identifier = 'identifier';
    case Digest = 'digest';
    case Text = 'text';
    case PersonalData = 'personal_data';
}

enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DataContract
{
    /**
     * @param list<DataClass> $returns
     * @param list<string> $neverReturns
     * @param list<string> $sources
     */
    public function __construct(
        public array $returns,
        public array $neverReturns = [],
        public array $sources = [],
        public string $doc = '',
    ) {
    }
}

final readonly class Finding
{
    public function __construct(
        public string $code,
        public Severity $severity,
        public string $message,
        public ?string $subject = null,
    ) {
    }

    /**
     * @return array{code: string, severity: string, message: string, subject?: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'severity' => $this->severity->value, 'message' => $this->message];
    }
}

final readonly class NotAvailable
{
    public function __construct(
        public string $subject,
        public string $reason,
    ) {
    }

    /**
     * @return array{subject: string, reason: string}
     */
    public function toArray(): array
    {
        return ['subject' => $this->subject, 'reason' => $this->reason];
    }
}

final readonly class CheckResult
{
    /**
     * @param list<Finding> $findings
     * @param array<string, bool|int|float|string|null> $metrics
     * @param list<NotAvailable> $notAvailable
     */
    public function __construct(
        public array $findings = [],
        public array $metrics = [],
        public array $notAvailable = [],
    ) {
    }

    /**
     * @return array{
     *     findings: list<array{code: string, severity: string, message: string, subject?: string}>,
     *     metrics: array<string, bool|int|float|string|null>,
     *     not_available: list<array{subject: string, reason: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $this->findings),
            'metrics' => $this->metrics,
            'not_available' => array_map(static fn (NotAvailable $item): array => $item->toArray(), $this->notAvailable),
        ];
    }
}
