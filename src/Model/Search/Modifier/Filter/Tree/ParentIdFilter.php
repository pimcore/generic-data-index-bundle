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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

final class ParentIdFilter implements SearchModifierInterface
{
    public function __construct(
        private readonly int $parentId = 1
    ) {
        $this->validate();
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    private function validate(): void
    {
        if ($this->parentId < 1) {
            throw new InvalidModifierException("Parent ID must be a positive integer.");
        }
    }
}
