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
