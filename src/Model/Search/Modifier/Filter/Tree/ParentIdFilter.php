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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Validater\HasPositiveIdTrait;

final class ParentIdFilter implements SearchModifierInterface
{
    use HasPositiveIdTrait;
    public function __construct(
        private readonly int $parentId = 1
    ) {
        $this->validatePositiveInt($this->parentId);
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }
}
