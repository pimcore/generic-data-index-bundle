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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot;

/**
 * @internal
 */
final readonly class ImportResult
{
    /**
     * @param ImportedIndex[] $imported
     * @param array<string, string> $skipped short name => reason
     * @param string[] $notices
     */
    public function __construct(
        private string $name,
        private Manifest $manifest,
        private CompatibilityReport $report,
        private array $imported,
        private array $skipped,
        private array $notices,
        private bool $dryRun,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getReport(): CompatibilityReport
    {
        return $this->report;
    }

    /**
     * @return ImportedIndex[]
     */
    public function getImported(): array
    {
        return $this->imported;
    }

    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /**
     * @return string[]
     */
    public function getNotices(): array
    {
        return $this->notices;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * True when every imported index is complete. A dry run always reports true: it only
     * plans what would be imported and has no actual counts to compare.
     */
    public function isSuccessful(): bool
    {
        if ($this->dryRun) {
            return true;
        }

        foreach ($this->imported as $index) {
            if (!$index->isComplete()) {
                return false;
            }
        }

        return true;
    }
}
