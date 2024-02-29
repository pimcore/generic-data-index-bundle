<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset;

interface AdapterInterface
{
    public function setType(string $type): self;
    public function getType(): string;
    public function getIndexMapping(): array;

    /**
     * Used to normalize the data for the search index
     */
    public function normalize(mixed $value): mixed;
}