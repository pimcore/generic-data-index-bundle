<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort;

class FieldSortList
{
    public function __construct(
        /** @var FieldSort[] */
        private array $sort = [],
    )
    {
    }

    public function addSort(FieldSort $sort = null): FieldSortList
    {
        if ($sort !== null) {
            $this->sort[] = $sort;
        }

        return $this;
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
