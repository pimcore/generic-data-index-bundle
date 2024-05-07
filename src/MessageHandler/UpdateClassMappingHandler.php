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

namespace Pimcore\Bundle\GenericDataIndexBundle\MessageHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateClassMappingMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class UpdateClassMappingHandler
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly DataObjectIndexHandler $dataObjectMappingHandler,
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
    ) {
    }

    public function __invoke(UpdateClassMappingMessage $message): void
    {
        $classDefinition = $message->getClassDefinition();
        $dispatch = $message->isDispatchQueueMessages();

        try {
            $mappingProperties = $this->dataObjectMappingHandler->getMappingProperties($classDefinition);
            $currentCheckSum = $this->dataObjectMappingHandler->getClassMappingCheckSum($mappingProperties);
            $storedCheckSum = $this->settingsStoreService->getClassMappingCheckSum($classDefinition->getId());

            if ($storedCheckSum === $currentCheckSum) {
                return;
            }

            $this->dataObjectMappingHandler
                ->reindexMapping(
                    context: $classDefinition,
                    mappingProperties: $mappingProperties
                );

            $this->settingsStoreService->storeClassMapping(
                classDefinitionId: $classDefinition->getId(),
                data: $this->dataObjectMappingHandler->getClassMappingCheckSum(
                    $mappingProperties
                )
            );

            if ($dispatch) {
                $this->enqueueService
                    ->enqueueByClassDefinition($classDefinition)
                    ->dispatchQueueMessages();
            }

        } catch (Exception $exception) {
            $this->logger->error('Updating class definition mapping failed: ' . $exception);
        }
    }
}
