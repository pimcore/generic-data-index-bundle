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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation;

class AggregationList
{
    public function __construct(
        /** @var Aggregation[] */
        private array $aggregations = [],
    ) {
    }

    public function addAggregation(Aggregation $aggregation = null): AggregationList
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
