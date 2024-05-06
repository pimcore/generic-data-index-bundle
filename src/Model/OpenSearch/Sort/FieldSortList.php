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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort;

final class FieldSortList
{
    public function __construct(
        /** @var FieldSort[] */
        private array $sort = [],
    ) {
    }

    public function addSort(FieldSort $sort = null): FieldSortList
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
