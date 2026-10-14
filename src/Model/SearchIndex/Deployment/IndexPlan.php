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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment;

/**
 * What generic-data-index:deployment:reindex detected for one index and what it does about it.
 *
 * @internal
 */
final readonly class IndexPlan
{
    /**
     * @param string[] $reasons
     */
    public function __construct(
        private IndexStateTarget $target,
        private IndexState $currentState,
        private bool $indexMissing,
        private bool $mappingChanged,
        private bool $settingsChanged,
        private bool $dataVersionChanged,
        private bool $baselineMissing,
        private array $reasons,
    ) {
    }

    public function getTarget(): IndexStateTarget
    {
        return $this->target;
    }

    public function getCurrentState(): IndexState
    {
        return $this->currentState;
    }

    public function isIndexMissing(): bool
    {
        return $this->indexMissing;
    }

    public function isMappingChanged(): bool
    {
        return $this->mappingChanged;
    }

    public function isSettingsChanged(): bool
    {
        return $this->settingsChanged;
    }

    public function isDataVersionChanged(): bool
    {
        return $this->dataVersionChanged;
    }

    /**
     * At least one value was never stored. Without any other change only the current values are
     * stored, so enabling the checks does not rebuild every index on the first run.
     */
    public function isBaselineMissing(): bool
    {
        return $this->baselineMissing;
    }

    /**
     * @return string[]
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    public function needsNativeReindex(): bool
    {
        return !$this->indexMissing && ($this->mappingChanged || $this->settingsChanged);
    }

    public function needsRebuild(): bool
    {
        return $this->indexMissing || $this->mappingChanged || $this->dataVersionChanged;
    }

    public function isBaselineOnly(): bool
    {
        return $this->baselineMissing && !$this->needsNativeReindex() && !$this->needsRebuild();
    }

    public function isSkipped(): bool
    {
        return !$this->needsNativeReindex() && !$this->needsRebuild() && !$this->baselineMissing;
    }

    public function getActionLabel(): string
    {
        return match (true) {
            $this->indexMissing => 'create index, rebuild from database',
            $this->needsNativeReindex() && $this->needsRebuild() => 'native reindex, rebuild from database',
            $this->needsNativeReindex() => 'native reindex',
            $this->needsRebuild() => 'rebuild from database',
            $this->isBaselineOnly() => 'store baseline',
            default => 'skip',
        };
    }
}
