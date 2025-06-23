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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\QueryType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Traits\SimplifySingleTypesTrait;

final class QueryList
{
    use SimplifySingleTypesTrait;

    private ?BoolQuery $boolQuery = null;

    public function __construct(
        /** @var QueryInterface[] */
        private array $queries = [],
    ) {
    }

    public function addQuery(?QueryInterface $query = null): QueryList
    {
        if ($query instanceof BoolQuery && !$query->isEmpty()) {
            if ($this->boolQuery !== null) {
                $this->boolQuery->merge($query);

                return $this;
            }
            $this->boolQuery = $query;
        }

        if ($query !== null && !$query->isEmpty()) {
            $this->queries[] = $query;
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->queries);
    }

    public function toArray(): array
    {
        $result =  [];

        $this->combineToBoolQuery();

        foreach ($this->queries as $query) {
            $queryType = $query->getType() instanceof QueryType ? $query->getType()->value : $query->getType();
            $result[$queryType] = $result[$queryType] ?? [];
            $result[$queryType][] = $query->toArray();
        }

        return $this->simplifySingleTypes($result);
    }

    private function combineToBoolQuery(): void
    {
        if (count($this->queries) < 2) {
            return;
        }

        $this->boolQuery ??= new BoolQuery();

        foreach ($this->queries as $query) {
            if (!$query instanceof BoolQuery && !$query->isEmpty()) {
                $this->boolQuery->addCondition(ConditionType::FILTER->value, $query->toArray(true));
            }
        }
        $this->queries = [$this->boolQuery];
    }
}
