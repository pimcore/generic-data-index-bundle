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
 *
 * The declarations below are copied verbatim from pimcore/pimcore 2026.x lib/Contracts/Diagnostics (one class per
 * file there) so that the analysis sees the same signatures and validation as the real contract. Re-copy them when
 * the core contract changes.
 */

namespace Pimcore\Contracts\Diagnostics;

use function array_is_list;
use Attribute;
use InvalidArgumentException;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * A read-only diagnostic any bundle may provide. Core registers and runs nothing: a consumer of the checks collects
 * the services tagged with {@see self::TAG}; without such a consumer a check is never run.
 */
interface CheckInterface
{
    public const string TAG = 'pimcore.diagnostics.check';

    /**
     * Stable snake_case identifier, unique across all checks. Prefix it with a short bundle name,
     * e.g. generic_data_index.index_queue_backlog.
     */
    public function getId(): string;

    /**
     * Must not change anything on the instance and must not put secrets or personal data into the result.
     */
    public function run(): CheckResult;
}

/**
 * The kinds of data a diagnostic check may deliver. There is deliberately no case for secrets: credentials,
 * tokens, keys and DSNs with passwords can never be declared and must never be returned.
 */
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

/**
 * Declares which data a diagnostic check delivers. Core only validates its shape; a consumer of the checks may
 * enforce it further.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DataContract
{
    /**
     * @param list<DataClass> $returns Data classes the check delivers; must not be empty
     * @param list<string> $neverReturns Fields or kinds of value present in the source that must never be returned
     * @param list<string> $sources Where the check reads from (table, command, service, config key)
     * @param string $doc Anchor or relative path (with anchor) into the documentation of the providing bundle, or a URL; not validated here
     */
    public function __construct(
        public array $returns,
        public array $neverReturns = [],
        public array $sources = [],
        public string $doc = '',
    ) {
        if ($returns === []) {
            throw new InvalidArgumentException('"returns" must contain at least one DataClass case.');
        }

        foreach ($returns as $class) {
            if (!$class instanceof DataClass) {
                throw new InvalidArgumentException('Every entry of "returns" must be a DataClass case.');
            }
        }

        foreach (['neverReturns' => $neverReturns, 'sources' => $sources] as $name => $entries) {
            foreach ($entries as $entry) {
                if (!is_string($entry) || $entry === '') {
                    throw new InvalidArgumentException(sprintf('Every entry of "%s" must be a non-empty string.', $name));
                }
            }
        }
    }
}

/**
 * One observation of a check. The message is written by the check author as a fixed text and must never
 * embed values read from the instance (credentials, queries, personal data).
 */
final readonly class Finding
{
    public function __construct(
        public string $code,
        public Severity $severity,
        public string $message,
        public ?string $subject = null,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('"code" must be a non-empty string.');
        }

        if ($message === '') {
            throw new InvalidArgumentException('"message" must be a non-empty string.');
        }

        if ($subject === '') {
            throw new InvalidArgumentException('"subject" must be null or a non-empty string.');
        }
    }

    /**
     * @return array{code: string, severity: string, message: string, subject?: string}
     */
    public function toArray(): array
    {
        $data = ['code' => $this->code, 'severity' => $this->severity->value, 'message' => $this->message];
        if ($this->subject !== null) {
            $data['subject'] = $this->subject;
        }

        return $data;
    }
}

/**
 * Something a check could not determine, e.g. because the database user lacks a privilege.
 */
final readonly class NotAvailable
{
    public function __construct(
        public string $subject,
        public string $reason,
    ) {
        if ($subject === '') {
            throw new InvalidArgumentException('"subject" must be a non-empty string.');
        }

        if ($reason === '') {
            throw new InvalidArgumentException('"reason" must be a non-empty string.');
        }
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
        if (!array_is_list($findings)) {
            throw new InvalidArgumentException('"findings" must be a list.');
        }

        if (!array_is_list($notAvailable)) {
            throw new InvalidArgumentException('"notAvailable" must be a list.');
        }

        foreach ($findings as $finding) {
            if (!$finding instanceof Finding) {
                throw new InvalidArgumentException('Every entry of "findings" must be a Finding.');
            }
        }

        foreach ($notAvailable as $item) {
            if (!$item instanceof NotAvailable) {
                throw new InvalidArgumentException('Every entry of "notAvailable" must be a NotAvailable.');
            }
        }

        foreach ($metrics as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Every key of "metrics" must be a string.');
            }

            if ($value !== null && !is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
                throw new InvalidArgumentException('Every value of "metrics" must be a scalar or null.');
            }

            if (is_float($value) && !is_finite($value)) {
                throw new InvalidArgumentException('Every float of "metrics" must be finite.');
            }
        }
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
            'not_available' => array_map(
                static fn (NotAvailable $item): array => $item->toArray(),
                $this->notAvailable,
            ),
        ];
    }
}
