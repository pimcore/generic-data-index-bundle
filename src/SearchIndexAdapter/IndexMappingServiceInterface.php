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

    public function getMappingForTextKeyword(array $attributes): array;

    public function getMappingForAdvancedImage(array $attributes): array;
}
