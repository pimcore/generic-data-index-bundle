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
        public string $name,
        public Manifest $manifest,
        public CompatibilityReport $report,
        public array $imported,
        public array $skipped,
        public array $notices,
        public bool $dryRun,
    ) {
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
