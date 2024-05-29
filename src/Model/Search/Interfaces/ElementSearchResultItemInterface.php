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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;

interface ElementSearchResultItemInterface
{
    public function getElementType(): ElementType;

    public function getId(): int;

    public function setId(int $id): ElementSearchResultItemInterface;

    public function getParentId(): int;

    public function setParentId(int $parentId): ElementSearchResultItemInterface;

    public function getType(): string;

    public function setType(string $type): ElementSearchResultItemInterface;

    public function getKey(): string;

    public function setKey(string $key): ElementSearchResultItemInterface;

    public function getPath(): string;

    public function setPath(string $path): ElementSearchResultItemInterface;

    public function getFullPath(): string;

    public function setFullPath(string $fullPath): ElementSearchResultItemInterface;

    public function getUserOwner(): int;

    public function setUserOwner(int $userOwner): ElementSearchResultItemInterface;

    public function getUserModification(): ?int;

    public function setUserModification(?int $userModification): ElementSearchResultItemInterface;

    public function getLocked(): ?string;

    public function setLocked(?string $locked): ElementSearchResultItemInterface;

    public function isLocked(): bool;

    public function setIsLocked(bool $isLocked): ElementSearchResultItemInterface;

    public function getCreationDate(): ?int;

    public function setCreationDate(?int $creationDate): ElementSearchResultItemInterface;

    public function getModificationDate(): ?int;

    public function setModificationDate(?int $modificationDate): ElementSearchResultItemInterface;

    public function isHasChildren(): bool;

    public function setHasChildren(bool $hasChildren): ElementSearchResultItemInterface;

    public function getSearchIndexData(): array;

    public function setSearchIndexData(array $searchIndexData): ElementSearchResultItemInterface;

    public function getPermissions(): BasePermissions;
}
