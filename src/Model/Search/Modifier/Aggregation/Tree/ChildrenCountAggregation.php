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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Validater\HasPositiveIntArrayTrait;

class ChildrenCountAggregation implements SearchModifierInterface
{
    use HasPositiveIntArrayTrait;
    
    public function __construct(
        private readonly array $parentIds = [],
        private readonly string $aggregationName = 'children_count'
    ) {
        $this->validatePositiveIntArray($this->parentIds);
    }

    /**
     * @return int[]
     */
    public function getParentIds(): array
    {
        return $this->parentIds;
    }

    public function getAggregationName(): string
    {
        return $this->aggregationName;
    }
}
