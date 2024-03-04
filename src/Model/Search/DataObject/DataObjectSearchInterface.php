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

use Pimcore\Model\DataObject\ClassDefinition;

interface DataObjectSearchInterface
{
    public function getClassDefinition(): ?ClassDefinition;

    public function setClassDefinition(ClassDefinition $classDefinition): void;
}
