<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\QueryType;

final class Query implements QueryInterface
{
    public function __construct(
        private readonly string $type,
        private readonly array $params = [],
    ) {
    }

    public function getType(): string
    {
       return $this->type;
    }

    public function isEmpty(): bool
    {
        return empty($this->params);
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function toArray(): array
    {
        return $this->params;
    }


}