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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Model\User;

interface SearchInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    /**
     * @return SearchModifierInterface[]
     */
    public function getModifiers(): array;

    public function addModifier(SearchModifierInterface $modifier);

    public function setUser(User $user);

    public function getUser(): ?User;

    public function getPage(): int;

    public function setPage(int $page): SearchInterface;

    public function getPageSize(): int;

    public function setPageSize(int $pageSize): SearchInterface;
}
