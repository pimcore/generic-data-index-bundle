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

    public function setClassDefinition(ClassDefinition $classDefinition): self
    {
        $this->classDefinition = $classDefinition;

        return $this;
    }
}
