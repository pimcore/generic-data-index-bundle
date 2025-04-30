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

namespace Pimcore\Bundle\GenericDataIndexBundle\Message;

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final readonly class UpdateClassMappingMessage
{
    public function __construct(
        private ClassDefinition $classDefinition,
        private bool $dispatchQueueMessages = false
    ) {
    }

    public function getClassDefinition(): ClassDefinition
    {
        return $this->classDefinition;
    }

    public function isDispatchQueueMessages(): bool
    {
        return $this->dispatchQueueMessages;
    }
}
