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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\ClassificationStore;

use Pimcore\Model\DataObject\ClassDefinition\Data;

/**
 * Which slot of a classification store key one indexed entry is written from, as that slot is
 * addressed on any element's own store: the key itself, and the qualifier the entry carries.
 *
 * All of it stays constant while the inheritance walk climbs the parent chain - only the element
 * being asked changes - so it travels as one value rather than as a parameter per coordinate.
 *
 * @internal
 */
final class KeyEntrySlot
{
    /**
     * @param Data $definition the key's resolved field definition, which decides what counts as empty
     * @param string $storeKey the name of the classification store field on the class
     * @param string|null $variant the declared variant of a sibling entry, null for the bare entry
     * @param bool $multiValue whether the key's declaration permits a list, which is what decides
     *                         whether the bare entry is read as one
     */
    public function __construct(
        private readonly Data $definition,
        private readonly string $storeKey,
        private readonly int $groupId,
        private readonly int $keyId,
        private readonly ?string $variant,
        private readonly bool $multiValue,
    ) {
    }

    public function getDefinition(): Data
    {
        return $this->definition;
    }

    public function getStoreKey(): string
    {
        return $this->storeKey;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getKeyId(): int
    {
        return $this->keyId;
    }

    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function isMultiValue(): bool
    {
        return $this->multiValue;
    }
}
