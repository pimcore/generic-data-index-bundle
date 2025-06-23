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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\ValueObject\Collection\ArrayOfPositiveIntegers;

final class ChildrenCountAggregation implements SearchModifierInterface
{
    private ArrayOfPositiveIntegers $parentIds;

    public function __construct(
        array $parentIds = [],
        private readonly string $aggregationName = 'children_count'
    ) {
        $this->parentIds = new ArrayOfPositiveIntegers($parentIds);
    }

    /**
     * @return int[]
     */
    public function getParentIds(): array
    {
        return $this->parentIds->getValue();
    }

    public function getAggregationName(): string
    {
        return $this->aggregationName;
    }
}
