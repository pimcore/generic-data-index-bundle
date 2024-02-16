<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final class OrderByFullPath implements SearchModifierInterface
{
    public function __construct(
        private readonly SortDirection $direction = SortDirection::ASC
    ) {
    }

    public function getDirection(): SortDirection
    {
        return $this->direction;
    }
}