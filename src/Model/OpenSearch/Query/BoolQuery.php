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
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits\QueryObjectsToArrayTrait;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits\SimplifySingleTypesTrait;

class BoolQuery implements QueryInterface
{
    use SimplifySingleTypesTrait;
    use QueryObjectsToArrayTrait;

    public function __construct(
        private array $params = [],
    ) {
    }

    public function getType(): QueryType
    {
        return QueryType::BOOL;
    }

    public function isEmpty(): bool
    {
        return empty($this->toArray());
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function addCondition(string $type, array $params): BoolQuery
    {
        $this->params[$type] = $this->params[$type] ?? [];
        if (!empty($this->params[$type]) && !array_is_list($this->params[$type])) {
            $this->params[$type] = [$this->params[$type]];
        }
        if (array_is_list($params)) {
            $this->params[$type] = array_merge($this->params[$type], $params);
        } else {
            $this->params[$type][] = $params;
        }

        return $this;
    }

    public function merge(BoolQuery $boolQuery): BoolQuery
    {
        foreach ($boolQuery->toArray() as $type => $params) {
            $this->addCondition($type, $params);
        }

        return $this;
    }

    public function toArray(bool $withType = false): array
    {
        $result = $this->convertQueryObjectsToArray($this->getParams());
        $result = $this->simplifySingleTypes($result);

        if ($withType) {
            return [$this->getType()->value => $result];
        }

        return $result;
    }
}
