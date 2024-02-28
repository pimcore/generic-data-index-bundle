<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */


namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

use Pimcore\Model\DataObject\ClassDefinition\Data;

/**
 * @internal
 */
interface IndexMappingServiceInterface
{
    /**
     * @param Data[] $fieldDefinitions
     */
    public function getMappingForFieldDefinitions(array $fieldDefinitions);
}