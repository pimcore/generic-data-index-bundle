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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status;

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;

/**
 * The completeness of one index at the time it was read: the derived state, the counts it is based on, and the
 * progress of a running rebuild.
 */
final readonly class IndexStatus
{
    /**
     * @param string $indexName alias name of the index, e.g. "pimcore_asset" or "pimcore_data-object_car"
     * @param string $label human-readable name, e.g. "class Car (CAR)" or "assets"
     * @param int $documentCount documents in the index
     * @param int $elementCount elements in the database that belong into the index
     * @param int $pendingEntries index queue entries of this index not yet processed
     * @param int $documentsWithoutChecksum documents a copy left without checksum; the rebuild rewrites them
     * @param bool $copyRunning an odd/even copy of this index is running in the search engine
     * @param bool|null $mappingCurrent the index mapping matches the current class definitions and configuration;
     *                                  null when no mapping state is stored (see deployment:reindex)
     * @param bool|null $settingsCurrent the index settings match the configured index_settings; null when unknown
     * @param int|null $rebuildQueuedAt when the last full rebuild was queued (milliseconds), null when none is pending
     * @param bool $rebuildEnqueuing the elements of the pending rebuild are still being queued; the pending entries
     *                               are not its full size yet
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private string $indexName,
        private string $label,
        private IndexStatusState $state,
        private int $documentCount,
        private int $elementCount,
        private int $pendingEntries,
        private int $documentsWithoutChecksum,
        private bool $copyRunning,
        private ?bool $mappingCurrent,
        private ?bool $settingsCurrent,
        private ?int $rebuildQueuedAt = null,
        private bool $rebuildEnqueuing = false,
    ) {
        if ($indexName === '') {
            throw new InvalidArgumentException('An index status needs an index name.');
        }
        if (min($documentCount, $elementCount, $pendingEntries, $documentsWithoutChecksum) < 0) {
            throw new InvalidArgumentException('Counts of an index status cannot be negative.');
        }
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getState(): IndexStatusState
    {
        return $this->state;
    }

    public function isComplete(): bool
    {
        return $this->state === IndexStatusState::COMPLETE;
    }

    /**
     * Progress of the running rebuild in percent (0 to 100), null when no rebuild runs. 0 while the elements of a
     * rebuild are still being queued.
     */
    public function getPercentage(): ?float
    {
        return match ($this->state) {
            IndexStatusState::REBUILDING => $this->rebuildProgress(),
            IndexStatusState::DEGRADED => $this->documentCount === 0
                ? 100.0
                : $this->clamp(100 * ($this->documentCount - $this->documentsWithoutChecksum) / $this->documentCount),
            default => null,
        };
    }

    public function getDocumentCount(): int
    {
        return $this->documentCount;
    }

    public function getElementCount(): int
    {
        return $this->elementCount;
    }

    public function getPendingEntries(): int
    {
        return $this->pendingEntries;
    }

    public function getDocumentsWithoutChecksum(): int
    {
        return $this->documentsWithoutChecksum;
    }

    public function isCopyRunning(): bool
    {
        return $this->copyRunning;
    }

    public function isMappingCurrent(): ?bool
    {
        return $this->mappingCurrent;
    }

    public function isSettingsCurrent(): ?bool
    {
        return $this->settingsCurrent;
    }

    public function getRebuildQueuedAt(): ?int
    {
        return $this->rebuildQueuedAt;
    }

    public function isRebuildEnqueuing(): bool
    {
        return $this->rebuildEnqueuing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->indexName,
            'label' => $this->label,
            'state' => $this->state->value,
            'complete' => $this->isComplete(),
            'percentage' => $this->getPercentage(),
            'documents' => $this->documentCount,
            'elements' => $this->elementCount,
            'pendingEntries' => $this->pendingEntries,
            'documentsWithoutChecksum' => $this->documentsWithoutChecksum,
            'copyRunning' => $this->copyRunning,
            'mappingCurrent' => $this->mappingCurrent,
            'settingsCurrent' => $this->settingsCurrent,
            'rebuildQueuedAt' => $this->rebuildQueuedAt,
            'rebuildEnqueuing' => $this->rebuildEnqueuing,
        ];
    }

    private function rebuildProgress(): float
    {
        if ($this->rebuildEnqueuing) {
            return 0.0;
        }
        if ($this->elementCount === 0) {
            return 100.0;
        }

        return $this->clamp(100 * ($this->elementCount - $this->pendingEntries) / $this->elementCount);
    }

    private function clamp(float $percentage): float
    {
        return round(max(0.0, min(100.0, $percentage)), 1);
    }
}
