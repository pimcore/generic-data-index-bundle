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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation;

final class AggregationList
{
    public function __construct(
        /** @var Aggregation[] */
        private array $aggregations = [],
    ) {
    }

    public function addAggregation(?Aggregation $aggregation = null): AggregationList
    {
        if ($aggregation !== null) {
            $this->aggregations[] = $aggregation;
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->aggregations);
    }

    public function toArray(): array
    {
        $result =  [];

        foreach ($this->aggregations as $aggregation) {
            $result[$aggregation->getName()] = $aggregation->getParams();
        }

        return $result;
    }
}
