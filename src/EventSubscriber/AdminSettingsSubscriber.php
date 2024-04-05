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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateLanguageSettingsMessage;
use Pimcore\Event\SystemEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final readonly class AdminSettingsSubscriber implements EventSubscriberInterface
{
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

        $this->messageBus->dispatch(
            new UpdateLanguageSettingsMessage(
                currentLanguages: $arguments['existingValues']['general']['valid_languages'],
                validLanguages: explode(',', $arguments['values']['general.validLanguages']),
            )
        );
    }
}
