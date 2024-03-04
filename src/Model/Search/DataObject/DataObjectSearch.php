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
