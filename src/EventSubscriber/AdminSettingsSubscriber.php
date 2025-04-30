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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateLanguageSettingsMessage;
use Pimcore\Event\SystemEvents;
use Pimcore\Helper\StopMessengerWorkersTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @internal
 */
final readonly class AdminSettingsSubscriber implements EventSubscriberInterface
{
    use StopMessengerWorkersTrait;

    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemEvents::SAVE_ACTION_SYSTEM_SETTINGS => 'updateSearchIndex',
        ];
    }

    /**
     * @throws Exception
     */
    public function updateSearchIndex(GenericEvent $event): void
    {
        $arguments = $event->getArguments();
        $this->stopMessengerWorkers();

        $this->messageBus->dispatch(
            new UpdateLanguageSettingsMessage(
                currentLanguages: $arguments['existingValues']['general']['valid_languages'],
                validLanguages: explode(',', $arguments['values']['general.validLanguages']),
            ),
            [new DelayStamp(2000)]
        );
    }
}
