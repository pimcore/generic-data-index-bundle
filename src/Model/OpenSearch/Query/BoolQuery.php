<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits\SimplifySingleTypesTrait;
use Pimcore\Bundle\PortalEngineBundle\Enum\Search\QueryType;

class BoolQuery implements QueryInterface
{
    use SimplifySingleTypesTrait;


    public function __construct(
        private array $params = [],
    )
    {
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
        if(!empty($this->params[$type]) && !array_is_list($this->params[$type])) {
            $this->params[$type] = [$this->params[$type]];
        }
        if(array_is_list($params)) {
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

    public function toArray(): array
    {
        return $this->simplifySingleTypes($this->getParams());
    }
}
