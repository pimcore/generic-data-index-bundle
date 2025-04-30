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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort;

final class FieldSortList
{
    public function __construct(
        /** @var FieldSort[] */
        private array $sort = [],
    ) {
    }

    public function addSort(?FieldSort $sort = null): FieldSortList
    {
        if ($sort !== null) {
            $this->sort[] = $sort;
        }

        return $this;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function isEmpty(): bool
    {
        return empty($this->sort);
    }

    public function toArray(): array
    {
        $result =  [];

        foreach ($this->sort as $sort) {
            $result[] = $sort->toArray();
        }

        return count($result) === 1 ? reset($result) : $result;
    }
}
