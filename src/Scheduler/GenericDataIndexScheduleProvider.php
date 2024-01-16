<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Scheduler;

use Pimcore\Bundle\GenericDataIndexBundle\Message\DispatchQueueMessagesMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('generic_data_index')]
class GenericDataIndexScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('1 minute', new DispatchQueueMessagesMessage())
        );
    }
}