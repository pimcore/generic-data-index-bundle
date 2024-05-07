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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Traits\PaginatedSearchTrait;
use Pimcore\Model\User;

/**
 * @internal
 */
class BaseSearch implements SearchInterface
{
    use PaginatedSearchTrait;

    /**
     * @var SearchModifierInterface[]
     */
    private array $modifiers = [];

    private ?User $user = null;

    private bool $aggregationsOnly = false;

    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    public function addModifier(SearchModifierInterface $modifier): self
    {
        $this->modifiers[] = $modifier;

        return $this;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function isAggregationsOnly(): bool
    {
        return $this->aggregationsOnly;
    }

    public function setAggregationsOnly(bool $aggregationsOnly): BaseSearch
    {
        $this->aggregationsOnly = $aggregationsOnly;

        return $this;
    }
}
