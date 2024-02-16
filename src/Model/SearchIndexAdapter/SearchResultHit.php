<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

class SearchResultHit
{
    public function __construct(
        private string         $id,
        private string         $index,
        private float          $score,
        private readonly array $source,
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getSource(): array
    {
        return $this->source;
    }


}