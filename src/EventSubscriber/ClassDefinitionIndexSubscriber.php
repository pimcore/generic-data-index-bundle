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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Event\DataObjectClassDefinitionEvents;
use Pimcore\Event\Model\DataObject\ClassDefinitionEvent;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class ClassDefinitionIndexSubscriber implements EventSubscriberInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly Installer $installer,
        private readonly DataObjectIndexHandler $dataObjectMappingHandler,
        private readonly EnqueueServiceInterface $enqueueService,
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
        if (!$this->installer->isInstalled()) {
            return;
        }

        $classDefinition = $event->getClassDefinition();
        $this->updateClassMapping($classDefinition);
    }

    /**
     * @throws Exception
     */
    public function updateClassDefinitionMapping(ClassDefinitionEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $classDefinition = $event->getClassDefinition();
        $mappingUpdated = $this->updateClassMapping($classDefinition);

        if ($mappingUpdated) {
            $this->enqueueService
                ->enqueueByClassDefinition($classDefinition)
                ->dispatchQueueMessages();
        }
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
    private function updateClassMapping(ClassDefinition $classDefinition): bool
    {
        $mappingProperties = $this->dataObjectMappingHandler->getMappingProperties($classDefinition);
        $currentCheckSum = $this->dataObjectMappingHandler->getClassMappingCheckSum($mappingProperties);
        $storedCheckSum = $this->settingsStoreService->getClassMappingCheckSum($classDefinition->getId());

        if ($storedCheckSum === $currentCheckSum) {
            return false;
        }

        $this->dataObjectMappingHandler
            ->updateMapping(
                context: $classDefinition,
                forceCreateIndex: true,
                mappingProperties: $mappingProperties
            );

        $this->settingsStoreService->storeClassMapping(
            classDefinitionId: $classDefinition->getId(),
            data: $this->dataObjectMappingHandler->getClassMappingCheckSum(
                $mappingProperties
            )
        );

        return true;
    }
}
