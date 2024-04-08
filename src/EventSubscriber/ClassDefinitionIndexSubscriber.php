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
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateClassMappingMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Event\DataObjectClassDefinitionEvents;
use Pimcore\Event\Model\DataObject\ClassDefinitionEvent;
use Pimcore\Helper\StopMessengerWorkersTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @internal
 */
final class ClassDefinitionIndexSubscriber implements EventSubscriberInterface
{
    use LoggerAwareTrait;
    use StopMessengerWorkersTrait;

    public function __construct(
        private readonly Installer $installer,
        private readonly DataObjectIndexHandler $dataObjectMappingHandler,
        private readonly MessageBusInterface $messageBus,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DataObjectClassDefinitionEvents::POST_UPDATE => 'updateClassDefinitionMapping',
            DataObjectClassDefinitionEvents::POST_ADD => 'addClassDefinitionMapping',
            DataObjectClassDefinitionEvents::POST_DELETE => 'deleteClassDefinitionIndex',
        ];
    }

    /**
     * @throws Exception
     */
    public function addClassDefinitionMapping(ClassDefinitionEvent $event): void
    {

        $this->handleMappingUpdate($event, false);
    }

    /**
     * @throws Exception
     */
    public function updateClassDefinitionMapping(ClassDefinitionEvent $event): void
    {
        $this->handleMappingUpdate($event, true);
    }

    public function deleteClassDefinitionIndex(ClassDefinitionEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $classDefinition = $event->getClassDefinition();

        try {
            $this->dataObjectMappingHandler
                ->deleteIndex($classDefinition);

            $this->settingsStoreService->removeClassMapping(
                classDefinitionId: $classDefinition->getId()
            );

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function handleMappingUpdate(ClassDefinitionEvent $event, bool $dispatch): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $this->stopMessengerWorkers();
        $classDefinition = $event->getClassDefinition();

        $this->messageBus->dispatch(
            new UpdateClassMappingMessage(
                classDefinition: $classDefinition,
                dispatchQueueMessages: $dispatch
            ),
            [new DelayStamp(2000)]
        );
    }
}
