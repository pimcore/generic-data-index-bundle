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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface ClassDefinitionReindexServiceInterface
{
    /**
     * @throws ClassDefinitionIndexUpdateFailedException
     */
    public function reindexClassDefinition(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged = false,
        bool $enqueueItems = false,
    ): bool;

    /**
     * Same as {@see reindexClassDefinition()}, returning what applying the mapping did, or null
     * when the class was skipped because its mapping is unchanged. Objects are queued when
     * $enqueueItems is set, and always when the result requires a rebuild: fields were left out of
     * the copy, the index had to be created empty, or a custom index handler applied the mapping.
     *
     * @throws ClassDefinitionIndexUpdateFailedException
     */
    public function reindexClassDefinitionMapping(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged = false,
        bool $enqueueItems = false,
    ): ?MappingUpdateResult;

    /**
     * Whether {@see reindexClassDefinition()} with $skipIfClassNotChanged would reindex the class.
     */
    public function isMappingChanged(ClassDefinition $classDefinition): bool;
}
