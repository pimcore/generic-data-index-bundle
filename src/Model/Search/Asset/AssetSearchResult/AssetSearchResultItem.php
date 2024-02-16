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
        private readonly int     $id,
        private readonly int     $parentId,
        private readonly string  $type,
        private readonly string  $key,
        private readonly string  $path,
        private readonly string  $fullPath,
        private readonly ?string $mimeType,
        private readonly int     $userOwner,
        private readonly int     $userModification,
        private readonly ?string $locked,
        private readonly bool    $isLocked,
        /** @var AssetMetaData[] */
        private readonly array   $metaData,
        private readonly ?int    $creationDate,
        private readonly ?int    $modificationDate,
        private readonly bool    $hasWorkflowWithPermissions,
        private readonly bool    $hasChildren,
        private readonly array   $searchIndexData,
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

    public function getKey(): string
    {
        return $this->key;
    }

    public function getFilename(): string
    {
        return $this->getKey();
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

    public function getUserOwner(): int
    {
        return $this->userOwner;
    }

    public function getUserModification(): int
    {
        return $this->userModification;
    }
    public function getLocked(): ?string
    {
        return $this->locked;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /** @return AssetMetaData[] */
    public function getMetaData(): array
    {
        return $this->metaData;
    }

    public function getCreationDate(): ?int
    {
        return $this->creationDate;
    }

    public function getModificationDate(): ?int
    {
        return $this->modificationDate;
    }

    public function hasWorkflowWithPermissions(): bool
    {
        return $this->hasWorkflowWithPermissions;
    }

    public function hasChildren(): bool
    {
        return $this->hasChildren;
    }

    /**
     * @return array Returns the full original data from the search index
     */
    public function getSearchIndexData(): array
    {
        return $this->searchIndexData;
    }
}
