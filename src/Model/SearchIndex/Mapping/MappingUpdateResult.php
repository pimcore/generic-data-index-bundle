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
     * @param string[] $rebuildPaths kept fields whose values only the rebuild from the database writes exactly
     */
    public function __construct(
        private array $strippedPaths = [],
        private array $keptPaths = [],
        private bool $recreated = false,
        private bool $outcomeUnknown = false,
        private array $rebuildPaths = [],
    ) {
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
     * @return string[]
     */
    public function getRebuildPaths(): array
    {
        return $this->rebuildPaths;
    }

    /**
     * Whether the index holds documents with missing or inexact values that only a rebuild from
     * the database restores, independent of whether the caller planned a rebuild anyway.
     */
    public function requiresRebuild(): bool
    {
        return $this->recreated || $this->outcomeUnknown || $this->strippedPaths !== [] || $this->rebuildPaths !== [];
    }
}
