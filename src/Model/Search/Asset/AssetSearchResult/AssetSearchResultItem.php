<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult;

final class AssetSearchResultItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $parentId,
        private readonly string $type,
        private readonly string $filename,
        private readonly string $path,
        private readonly string $fullPath,
        private readonly ?string $mimeType,
        private readonly bool $children,
        private readonly int $userOwner,
        private readonly int $userModification,
        private readonly ?int $creationDate,
        private readonly ?int $modificationDate,
        private readonly ?string $lock,
        private readonly bool $isLocked,
        private readonly array $searchIndexData,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFullPath(): string
    {
        return $this->fullPath;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function hasChildren(): bool
    {
        return $this->children;
    }

    public function getUserOwner(): int
    {
        return $this->userOwner;
    }

    public function getUserModification(): int
    {
        return $this->userModification;
    }

    public function getCreationDate(): ?int
    {
        return $this->creationDate;
    }

    public function getModificationDate(): ?int
    {
        return $this->modificationDate;
    }

    public function getLock(): ?string
    {
        return $this->lock;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /**
     * @return array Returns the full original data from the search index
     */
    public function getSearchIndexData(): array
    {
        return $this->searchIndexData;
    }
}
