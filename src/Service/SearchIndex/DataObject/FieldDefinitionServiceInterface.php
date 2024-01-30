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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter\AdapterInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface FieldDefinitionServiceInterface
{
    public function getFieldDefinitionAdapter(ClassDefinition\Data $fieldDefinition): ?AdapterInterface;
}
