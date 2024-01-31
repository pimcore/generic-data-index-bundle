<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\PortalEngineBundle\Enum\Search\QueryType;

interface QueryInterface
{
    public function getType(): QueryType;
    public function isEmpty(): bool;
    public function getParams(): array;
    public function toArray(): array;
}
