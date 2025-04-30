<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
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
        private array $aggregationResult = [],
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

    public function getAggregationResult(): array
    {
        return $this->aggregationResult;
    }
}
