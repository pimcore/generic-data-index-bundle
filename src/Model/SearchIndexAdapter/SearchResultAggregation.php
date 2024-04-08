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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

readonly class SearchResultAggregation
{
    public function __construct(
        private string $name,
        /** @var SearchResultAggregationBucket[] */
        private array $buckets,
        private int $otherDocCount,
        private int $docCountErrorUpperBound,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function getOtherDocCount(): int
    {
        return $this->otherDocCount;
    }

    public function getDocCountErrorUpperBound(): int
    {
        return $this->docCountErrorUpperBound;
    }
}
