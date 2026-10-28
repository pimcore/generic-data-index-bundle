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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping;

/**
 * What applying a new mapping did to an index.
 *
 * @internal
 */
final readonly class MappingUpdateResult
{
    /**
     * @param string[] $strippedPaths fields removed in the copy (dotted paths)
     * @param string[] $keptPaths changed fields whose old values were kept in the copy (dotted paths)
     * @param bool $recreated the index was created empty (missing index, or the copy was impossible)
     * @param bool $outcomeUnknown a custom handler applied the mapping without reporting what it did
     * @param bool $rebuildOwed an earlier mapping update of the index failed before its rebuild was queued
     * @param bool $mappingApplied this update applied a mapping; false when it only queued an owed rebuild
     */
    public function __construct(
        private array $strippedPaths = [],
        private array $keptPaths = [],
        private bool $recreated = false,
        private bool $outcomeUnknown = false,
        private bool $rebuildOwed = false,
        private bool $mappingApplied = true,
    ) {
    }

    /**
     * Nothing to apply, only the rebuild an earlier run owes: the caller still has to apply pending index settings.
     */
    public static function forOwedRebuildOnly(): self
    {
        return new self(rebuildOwed: true, mappingApplied: false);
    }

    public function withRebuildOwed(): self
    {
        return new self(
            $this->strippedPaths,
            $this->keptPaths,
            $this->recreated,
            $this->outcomeUnknown,
            true,
            $this->mappingApplied
        );
    }

    public function isMappingApplied(): bool
    {
        return $this->mappingApplied;
    }

    public function isRebuildOwed(): bool
    {
        return $this->rebuildOwed;
    }

    /**
     * @return string[]
     */
    public function getStrippedPaths(): array
    {
        return $this->strippedPaths;
    }

    /**
     * @return string[]
     */
    public function getKeptPaths(): array
    {
        return $this->keptPaths;
    }

    public function isRecreated(): bool
    {
        return $this->recreated;
    }

    public function isOutcomeUnknown(): bool
    {
        return $this->outcomeUnknown;
    }

    /**
     * Whether the index holds documents with missing values that only a rebuild from the
     * database restores, independent of whether the caller planned a rebuild anyway.
     */
    public function requiresRebuild(): bool
    {
        return $this->recreated || $this->outcomeUnknown || $this->rebuildOwed || $this->strippedPaths !== [];
    }
}
