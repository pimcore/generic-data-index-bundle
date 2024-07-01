<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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