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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\AggregationList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSortList;

final class Search implements DefaultSearchInterface
{
    public function __construct(
        private ?int $from = null,
        private ?int $size = null,
        private array|bool|string|null $source = null,
        private readonly QueryList $queryList = new QueryList(),
        private readonly AggregationList $aggregationList = new AggregationList(),
        private FieldSortList $sortList = new FieldSortList(),
        private bool $reverseItemOrder = false,
        private ?array $searchAfter = null,

    ) {
    }

    public function getFrom(): ?int
    {
        return $this->from;
    }

    public function setFrom(?int $from): DefaultSearchInterface
    {
        $this->from = $from;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): DefaultSearchInterface
    {
        $this->size = $size;

        return $this;
    }

    public function getSource(): bool|array|string|null
    {
        return $this->source;
    }

    public function setSource(bool|array|string|null $source): DefaultSearchInterface
    {
        $this->source = $source;

        return $this;
    }

    public function addQuery(?QueryInterface $query = null): DefaultSearchInterface
    {
        $this->queryList->addQuery($query);

        return $this;
    }

    public function addSort(FieldSort $sort): DefaultSearchInterface
    {
        $this->sortList->addSort($sort);

        return $this;
    }

    public function addAggregation(Aggregation $aggregation): DefaultSearchInterface
    {
        $this->aggregationList->addAggregation($aggregation);

        return $this;
    }

    public function getQueryList(): QueryList
    {
        return $this->queryList;
    }

    public function getSortList(): FieldSortList
    {
        return $this->sortList;
    }

    public function setSortList(FieldSortList $sortList): DefaultSearchInterface
    {
        $this->sortList = $sortList;

        return $this;
    }

    public function isReverseItemOrder(): bool
    {
        return $this->reverseItemOrder;
    }

    public function setReverseItemOrder(bool $reverseItemOrder): DefaultSearchInterface
    {
        $this->reverseItemOrder = $reverseItemOrder;

        return $this;
    }

    public function getSearchAfter(): ?array
    {
        return $this->searchAfter;
    }

    public function setSearchAfter(?array $searchAfter): DefaultSearchInterface
    {
        $this->searchAfter = is_array($searchAfter) && !empty($searchAfter) ? $searchAfter : null;

        return $this;
    }

    public function toArray(): array
    {
        $result = [
            'from' => $this->getSearchAfter() !== null ? null : $this->from,
            'size' => $this->size,
            '_source' => $this->source,
        ];

        foreach ($result as $key => $value) {
            if ($value === null) {
                unset($result[$key]);
            }
        }

        if (!$this->queryList->isEmpty()) {
            $result['query'] = $this->queryList->toArray();
        }

        if (!$this->aggregationList->isEmpty()) {
            $result['aggs'] = $this->aggregationList->toArray();
        }

        if (!$this->sortList->isEmpty()) {
            $result['sort'] = $this->sortList->toArray();
        }

        if ($this->getSearchAfter() !== null) {
            $result['search_after'] = $this->getSearchAfter();
        }

        return $result;
    }
}
