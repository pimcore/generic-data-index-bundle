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
