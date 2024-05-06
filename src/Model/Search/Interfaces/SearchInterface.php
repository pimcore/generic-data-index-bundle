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

    public function isAggregationsOnly(): bool;

    /**
     * Skips the result items and only returns the aggregations
     */
    public function setAggregationsOnly(bool $aggregationsOnly): SearchInterface;
}
