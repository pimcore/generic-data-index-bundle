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
    /**
     * @return SearchModifierInterface[]
     */
    public function getModifiers(): array;

    public function addModifier(SearchModifierInterface $modifier): self;

    public function setUser(User $user): self;

    public function getUser(): ?User;
}
