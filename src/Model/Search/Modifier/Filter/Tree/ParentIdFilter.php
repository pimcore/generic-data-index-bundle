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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ParentIdFilter implements SearchModifierInterface
{
    public function __construct(
        #[Assert\Positive]
        private readonly int $parentId = 1
    ) {
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }
}
