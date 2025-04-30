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
}
