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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult;

use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;

class DataObjectSearchResultItem
{
    private int $id;

    private int $parentId;

    private string $type;

    private string $key;

    private string $path;

    private string $fullPath;

    private int $userOwner;

    private int $userModification;

    private ?string $locked;

    private bool $isLocked;

    private ?int $creationDate;

    private ?int $modificationDate;

    private bool $workflowWithPermissions;

    private bool $hasChildren;

    private array $searchIndexData;

    private DataObjectPermission $permissions;

    public function __construct(
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): DataObjectSearchResultItem
    {
        $this->id = $id;

        return $this;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): DataObjectSearchResultItem
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): DataObjectSearchResultItem
    {
        $this->type = $type;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): DataObjectSearchResultItem
    {
        $this->key = $key;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): DataObjectSearchResultItem
    {
        $this->path = $path;

        return $this;
    }

    public function getFullPath(): string
    {
        return $this->fullPath;
    }

    public function setFullPath(string $fullPath): DataObjectSearchResultItem
    {
        $this->fullPath = $fullPath;

        return $this;
    }

    public function getUserOwner(): int
    {
        return $this->userOwner;
    }

    public function setUserOwner(int $userOwner): DataObjectSearchResultItem
    {
        $this->userOwner = $userOwner;

        return $this;
    }

    public function getUserModification(): int
    {
        return $this->userModification;
    }

    public function setUserModification(int $userModification): DataObjectSearchResultItem
    {
        $this->userModification = $userModification;

        return $this;
    }

    public function getLocked(): ?string
    {
        return $this->locked;
    }

    public function setLocked(?string $locked): DataObjectSearchResultItem
    {
        $this->locked = $locked;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): DataObjectSearchResultItem
    {
        $this->isLocked = $isLocked;

        return $this;
    }

    public function getCreationDate(): ?int
    {
        return $this->creationDate;
    }

    public function setCreationDate(?int $creationDate): DataObjectSearchResultItem
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getModificationDate(): ?int
    {
        return $this->modificationDate;
    }

    public function setModificationDate(?int $modificationDate): DataObjectSearchResultItem
    {
        $this->modificationDate = $modificationDate;

        return $this;
    }

    public function isHasWorkflowWithPermissions(): bool
    {
        return $this->workflowWithPermissions;
    }

    public function setHasWorkflowWithPermissions(bool $workflowWithPermissions): DataObjectSearchResultItem
    {
        $this->workflowWithPermissions = $workflowWithPermissions;

        return $this;
    }

    public function isHasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function setHasChildren(bool $hasChildren): DataObjectSearchResultItem
    {
        $this->hasChildren = $hasChildren;

        return $this;
    }

    public function getSearchIndexData(): array
    {
        return $this->searchIndexData;
    }

    public function setSearchIndexData(array $searchIndexData): DataObjectSearchResultItem
    {
        $this->searchIndexData = $searchIndexData;

        return $this;
    }

    public function getPermissions(): DataObjectPermission
    {
        return $this->permissions;
    }

    public function setPermissions(DataObjectPermission $permissions): DataObjectSearchResultItem
    {
        $this->permissions = $permissions;

        return $this;
    }
}
