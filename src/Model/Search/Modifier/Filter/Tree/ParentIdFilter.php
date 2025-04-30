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
