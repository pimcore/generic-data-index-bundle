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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits\SimplifySingleTypesTrait;

final class QueryList
{
    use SimplifySingleTypesTrait;

    private ?BoolQuery $boolQuery = null;

    public function __construct(
        /** @var QueryInterface[] */
        private array $queries = [],
    ) {
    }

    public function addQuery(QueryInterface $query = null): QueryList
    {
        if ($query instanceof BoolQuery && !$query->isEmpty()) {
            if($this->boolQuery !== null) {
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

        foreach ($this->queries as $query) {
            $queryType = $query->getType()->value;
            $result[$queryType] = $result[$queryType] ?? [];
            $result[$queryType][] = $query->toArray();
        }

        return $this->simplifySingleTypes($result);
    }
}
