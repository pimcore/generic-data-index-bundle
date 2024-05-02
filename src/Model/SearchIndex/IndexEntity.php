<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;

final readonly class IndexEntity
{
    public function __construct(
        private string $entityName,
        private string $indexName,
        private ?IndexType $indexType,
    )
    {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getIndexType(): ?IndexType
    {
        return $this->indexType;
    }
}