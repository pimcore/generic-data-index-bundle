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
