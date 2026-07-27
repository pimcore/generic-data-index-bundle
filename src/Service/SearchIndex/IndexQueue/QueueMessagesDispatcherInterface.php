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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

/**
 * @internal
 */
interface QueueMessagesDispatcherInterface
{
    public function dispatchQueueMessages(bool $synchronously = false): void;

    public function messageShouldBeTriggered(): bool;

    public function pendingMessageExists(): bool;

    public function clearPendingState(): void;
}
