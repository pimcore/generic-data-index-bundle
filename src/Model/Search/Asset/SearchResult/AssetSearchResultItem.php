<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\AssetLazyLoadingHandlerInterface;

class AssetSearchResultItem implements ElementSearchResultItemInterface
{
    private int $id;

    private int $parentId;

    private string $type;

    private string $key;

    private string $path;

    private string $fullPath;

    private ?string $mimeType;

    private ?int $fileSize;

    private int $userOwner;

    private ?int $userModification;

    private ?string $locked;

    private bool $isLocked;

    /** @var AssetMetaData[] */
    private array $metaData;

    private ?int $creationDate;

    private ?int $modificationDate;

    private bool $workflowWithPermissions;

    private bool $hasChildren;

    private array $searchIndexData;

    private AssetPermissions $permissions;

    private readonly AssetLazyLoadingHandlerInterface $lazyLoadingHandler;

    public function getElementType(): ElementType
    {
        return ElementType::ASSET;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): AssetSearchResultItem
    {
        $this->id = $id;

        return $this;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): AssetSearchResultItem
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): AssetSearchResultItem
    {
        $this->type = $type;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): AssetSearchResultItem
    {
        $this->key = $key;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): AssetSearchResultItem
    {
        $this->path = $path;

        return $this;
    }

    public function getFullPath(): string
    {
        return $this->fullPath;
    }

    public function setFullPath(string $fullPath): AssetSearchResultItem
    {
        $this->fullPath = $fullPath;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): AssetSearchResultItem
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getFileSize(): ?int
    {
        if (!isset($this->fileSize)) {
            $this->lazyLoad();
        }

        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): AssetSearchResultItem
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getUserOwner(): int
    {
        return $this->userOwner;
    }

    public function setUserOwner(int $userOwner): AssetSearchResultItem
    {
        $this->userOwner = $userOwner;

        return $this;
    }

    public function getUserModification(): ?int
    {
        return $this->userModification;
    }

    public function setUserModification(?int $userModification): AssetSearchResultItem
    {
        $this->userModification = $userModification;

        return $this;
    }

    public function getLocked(): ?string
    {
        return $this->locked;
    }

    public function setLocked(?string $locked): AssetSearchResultItem
    {
        $this->locked = $locked;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): AssetSearchResultItem
    {
        $this->isLocked = $isLocked;

        return $this;
    }

    /**
     * This will deliver searchable metadata only.
     * Custom metadata which is not relevant for the asset grid will not be included.
     */
    public function getMetaData(): array
    {
        return $this->metaData;
    }

    public function setMetaData(array $metaData): AssetSearchResultItem
    {
        $this->metaData = $metaData;

        return $this;
    }

    public function getCreationDate(): ?int
    {
        return $this->creationDate;
    }

    public function setCreationDate(?int $creationDate): AssetSearchResultItem
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getModificationDate(): ?int
    {
        return $this->modificationDate;
    }

    public function setModificationDate(?int $modificationDate): AssetSearchResultItem
    {
        $this->modificationDate = $modificationDate;

        return $this;
    }

    public function isHasWorkflowWithPermissions(): bool
    {
        if (!isset($this->workflowWithPermissions)) {
            $this->lazyLoad();
        }

        return $this->workflowWithPermissions;
    }

    public function setHasWorkflowWithPermissions(bool $workflowWithPermissions): AssetSearchResultItem
    {
        $this->workflowWithPermissions = $workflowWithPermissions;

        return $this;
    }

    public function isHasChildren(): bool
    {
        if (!isset($this->hasChildren)) {
            $this->lazyLoad();
        }

        return $this->hasChildren;
    }

    public function setHasChildren(bool $hasChildren): AssetSearchResultItem
    {
        $this->hasChildren = $hasChildren;

        return $this;
    }

    public function getSearchIndexData(): array
    {
        if (!isset($this->searchIndexData)) {
            $this->lazyLoad();
        }

        return $this->searchIndexData;
    }

    public function setSearchIndexData(array $searchIndexData): AssetSearchResultItem
    {
        $this->searchIndexData = $searchIndexData;

        return $this;
    }

    public function getPermissions(): AssetPermissions
    {
        return $this->permissions;
    }

    public function setPermissions(AssetPermissions $permissions): AssetSearchResultItem
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function withLazyLoadingHandler(?AssetLazyLoadingHandlerInterface $lazyLoadingHandler): AssetSearchResultItem
    {
        $clone = clone $this;
        $clone->lazyLoadingHandler = $lazyLoadingHandler;

        return $clone;
    }

    private function lazyLoad(): void
    {
        if (isset($this->lazyLoadingHandler)) {
            $this->lazyLoadingHandler->lazyLoad($this);
        }
    }
}
