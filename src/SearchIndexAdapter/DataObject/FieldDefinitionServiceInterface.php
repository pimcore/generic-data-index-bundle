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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject;

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface FieldDefinitionServiceInterface
{
    public function getFieldDefinitionAdapter(ClassDefinition\Data $fieldDefinition): ?AdapterInterface;

    public function normalizeValue(?ClassDefinition\Data $fieldDefinition, mixed $value): mixed;
}
