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
use Symfony\Component\Validator\Constraints as Assert;

class ChildrenCountAggregation implements SearchModifierInterface
{
    public function __construct(
        /** @var int[] $parentIds */
        #[Assert\All([
            new Assert\Type('int'),
            new Assert\Positive(),
        ])]
        private readonly array $parentIds = [],
        private readonly string $aggregationName = 'children_count'
    ) {
    }

    public function getParentIds(): array
    {
        return $this->parentIds;
    }

    public function getAggregationName(): string
    {
        return $this->aggregationName;
    }
}
