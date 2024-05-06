<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation;

final class AggregationList
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
