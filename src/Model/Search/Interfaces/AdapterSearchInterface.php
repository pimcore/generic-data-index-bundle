<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSortList;

interface AdapterSearchInterface
{
    public function getFrom(): ?int;

    public function setFrom(?int $from): AdapterSearchInterface;

    public function getSize(): ?int;

    public function setSize(?int $size): AdapterSearchInterface;

    public function getSortList(): FieldSortList;

    public function setSortList(FieldSortList $sortList): AdapterSearchInterface;

    public function isReverseItemOrder(): bool;

    public function setReverseItemOrder(bool $reverseItemOrder): AdapterSearchInterface;

    public function toArray(): array;
}
