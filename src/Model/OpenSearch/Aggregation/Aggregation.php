<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation;

use Pimcore\Bundle\PortalEngineBundle\Enum\Search\AggregationType;

class Aggregation
{
    public function __construct(
        private readonly string          $name,
        private readonly array           $params,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParams(): array
    {
        return $this->params;
    }

}
