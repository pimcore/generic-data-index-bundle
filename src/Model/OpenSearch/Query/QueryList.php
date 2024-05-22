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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\QueryType;
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

        foreach ($this->queries as $query) {
            $queryType = $query->getType() instanceof QueryType ? $query->getType()->value : $query->getType();
            $result[$queryType] = $result[$queryType] ?? [];
            $result[$queryType][] = $query->toArray();
        }

        return $this->simplifySingleTypes($result);
    }
}
