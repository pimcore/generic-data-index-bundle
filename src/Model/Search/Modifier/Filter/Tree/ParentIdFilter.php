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
use Pimcore\ValueObject\Integer\PositiveInteger;

final class ParentIdFilter implements SearchModifierInterface
{
    private PositiveInteger $parentId;

    public function __construct(int $parentId = 1)
    {
        $this->parentId = new PositiveInteger($parentId);
    }

    public function getParentId(): int
    {
        return $this->parentId->getValue();
    }
}
