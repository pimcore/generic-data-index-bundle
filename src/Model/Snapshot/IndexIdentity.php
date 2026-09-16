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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;

/**
 * The identity of a snapshot index, independent of any particular manifest entry or local index
 * target: an element type plus, for a data-object class index, the class id. Centralises the
 * identity rules every snapshot component needs (expected short name, duplicate detection, index
 * handler dispatch) so they cannot drift apart.
 *
 * @internal
 */
final readonly class IndexIdentity
{
    public function __construct(
        public string $elementType,
        public ?string $classId = null,
    ) {
    }

    public static function fromManifestIndex(ManifestIndex $index): self
    {
        return new self($index->elementType, $index->classId);
    }

    public static function forClass(string $classId): self
    {
        return new self(ElementType::DATA_OBJECT->value, $classId);
    }

    public static function forDataObjectFolder(): self
    {
        return new self(ElementType::DATA_OBJECT->value);
    }

    public static function forAsset(): self
    {
        return new self(ElementType::ASSET->value);
    }

    public static function forDocument(): self
    {
        return new self(ElementType::DOCUMENT->value);
    }

    public function isDataObject(): bool
    {
        return $this->elementType === ElementType::DATA_OBJECT->value;
    }

    public function isClassIndex(): bool
    {
        return $this->isDataObject() && $this->classId !== null;
    }

    public function isDataObjectFolder(): bool
    {
        return $this->isDataObject() && $this->classId === null;
    }

    public function isAsset(): bool
    {
        return $this->elementType === ElementType::ASSET->value;
    }

    public function isDocument(): bool
    {
        return $this->elementType === ElementType::DOCUMENT->value;
    }

    /**
     * The short index name this identity must have, or null for a class index: its short name is
     * `SearchIndexConfigService::CLASS_INDEX_PREFIX . <lowercase class name>`, which cannot be
     * derived from the class id alone.
     */
    public function expectedShortName(): ?string
    {
        return match (true) {
            $this->isAsset() => IndexName::ASSET->value,
            $this->isDocument() => IndexName::DOCUMENT->value,
            $this->isDataObjectFolder() => IndexName::DATA_OBJECT_FOLDER->value,
            default => null,
        };
    }

    /**
     * A class index accepts any short name that starts with the class-index prefix and is not
     * the folder index's own name; every other (singleton) identity accepts only its one
     * expected short name.
     */
    public function acceptsShortName(string $shortName): bool
    {
        if ($this->isClassIndex()) {
            return str_starts_with($shortName, SearchIndexConfigService::CLASS_INDEX_PREFIX)
                && $shortName !== IndexName::DATA_OBJECT_FOLDER->value;
        }

        return $shortName === $this->expectedShortName();
    }

    /**
     * A stable key identifying the local index this identity targets, for duplicate detection:
     * `dataObject:<classId>` (`dataObject:` for the folder index), or the bare element type for
     * every other (singleton) identity.
     */
    public function key(): string
    {
        if ($this->isDataObject()) {
            return 'dataObject:' . ($this->classId ?? '');
        }

        return $this->elementType;
    }

    public function equals(self $other): bool
    {
        return $this->elementType === $other->elementType && $this->classId === $other->classId;
    }

    public function elementTypeEnum(): ElementType
    {
        return ElementType::tryFrom($this->elementType)
            ?? throw new InvalidSnapshotException(sprintf('Unknown element type "%s"', $this->elementType));
    }
}
