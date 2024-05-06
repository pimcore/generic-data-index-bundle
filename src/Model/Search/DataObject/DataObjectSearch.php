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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\BaseSearch;
use Pimcore\Model\DataObject\ClassDefinition;

final class DataObjectSearch extends BaseSearch implements DataObjectSearchInterface
{
    private ?ClassDefinition $classDefinition = null;

    public function getClassDefinition(): ?ClassDefinition
    {
        return $this->classDefinition;
    }

    public function setClassDefinition(ClassDefinition $classDefinition): void
    {
        $this->classDefinition = $classDefinition;
    }
}
