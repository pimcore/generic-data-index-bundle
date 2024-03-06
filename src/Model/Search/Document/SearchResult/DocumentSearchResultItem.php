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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult;

use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermission;

class DocumentSearchResultItem
{
    private int $id;

    private int $parentId;

    private string $type;

    private string $key;

    private bool $published;

    private string $path;

    private string $fullPath;

    private int $userOwner;

    private ?int $userModification;

    private ?string $locked;

    private bool $isLocked;

    private ?int $creationDate;

    private ?int $modificationDate;

    private string $className;

    private bool $workflowWithPermissions;

    private bool $hasChildren;

    private array $searchIndexData;

    private DocumentPermission $permissions;

    public function __construct(
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): DocumentSearchResultItem
    {
        $this->id = $id;

        return $this;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): DocumentSearchResultItem
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): DocumentSearchResultItem
    {
        $this->type = $type;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): DocumentSearchResultItem
    {
        $this->key = $key;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): DocumentSearchResultItem
    {
        $this->published = $published;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): DocumentSearchResultItem
    {
        $this->path = $path;

        return $this;
    }

    public function getFullPath(): string
    {
        return $this->fullPath;
    }

    public function setFullPath(string $fullPath): DocumentSearchResultItem
    {
        $this->fullPath = $fullPath;

        return $this;
    }

    public function getUserOwner(): int
    {
        return $this->userOwner;
    }

    public function setUserOwner(int $userOwner): DocumentSearchResultItem
    {
        $this->userOwner = $userOwner;

        return $this;
    }

    public function getUserModification(): ?int
    {
        return $this->userModification;
    }

    public function setUserModification(?int $userModification): DocumentSearchResultItem
    {
        $this->userModification = $userModification;

        return $this;
    }

    public function getLocked(): ?string
    {
        return $this->locked;
    }

    public function setLocked(?string $locked): DocumentSearchResultItem
    {
        $this->locked = $locked;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(?bool $isLocked): DocumentSearchResultItem
    {
        $this->isLocked = (bool)$isLocked;

        return $this;
    }

    public function getCreationDate(): ?int
    {
        return $this->creationDate;
    }

    public function setCreationDate(?int $creationDate): DocumentSearchResultItem
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getModificationDate(): ?int
    {
        return $this->modificationDate;
    }

    public function setModificationDate(?int $modificationDate): DocumentSearchResultItem
    {
        $this->modificationDate = $modificationDate;

        return $this;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function setClassName(string $className): DocumentSearchResultItem
    {
        $this->className = $className;

        return $this;
    }

    public function isHasWorkflowWithPermissions(): bool
    {
        return $this->workflowWithPermissions;
    }

    public function setHasWorkflowWithPermissions(?bool $workflowWithPermissions): DocumentSearchResultItem
    {
        $this->workflowWithPermissions = (bool)$workflowWithPermissions;

        return $this;
    }

    public function isHasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function setHasChildren(bool $hasChildren): DocumentSearchResultItem
    {
        $this->hasChildren = $hasChildren;

        return $this;
    }

    public function getSearchIndexData(): array
    {
        return $this->searchIndexData;
    }

    public function setSearchIndexData(array $searchIndexData): DocumentSearchResultItem
    {
        $this->searchIndexData = $searchIndexData;

        return $this;
    }

    public function getPermissions(): DocumentPermission
    {
        return $this->permissions;
    }

    public function setPermissions(DocumentPermission $permissions): DocumentSearchResultItem
    {
        $this->permissions = $permissions;

        return $this;
    }
}
