<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ParentIdFilter implements SearchModifierInterface
{
    public function __construct(
        #[Assert\Positive]
        private readonly int $parentId = 1
    )
    {
    }
    public function getParentId(): int
    {
        return $this->parentId;
    }
}