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

namespace Pimcore\Bundle\GenericDataIndexBundle\Scheduler;

use Pimcore\Bundle\GenericDataIndexBundle\Message\DispatchQueueMessagesMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * @internal
 */
#[AsSchedule('generic_data_index')]
final readonly class GenericDataIndexScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private QueueMessagesDispatcher $queueMessagesDispatcher,
        private EventDispatcherInterface $eventDispatcher,
    ) {

    }

    public function getSchedule(): Schedule
    {
        return (new Schedule($this->eventDispatcher))->add(

            RecurringMessage::every('10 seconds', new DispatchQueueMessagesMessage())

        )->before(function (PreRunEvent $event) {
            if (
                $event->getMessage() instanceof DispatchQueueMessagesMessage
                && !$this->queueMessagesDispatcher->messageShouldBeTriggered()
            ) {
                $event->shouldCancel(true);
            }
        });
    }
}
