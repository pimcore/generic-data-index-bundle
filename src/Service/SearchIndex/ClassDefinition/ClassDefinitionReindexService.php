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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final readonly class ClassDefinitionReindexService implements ClassDefinitionReindexServiceInterface
{
    public function __construct(
        private DataObjectIndexHandler $dataObjectIndexHandler,
        private EnqueueServiceInterface $enqueueService,
        private SettingsStoreServiceInterface $settingsStoreService,
        private IndexIconUpdateServiceInterface $indexIconUpdateService,
    ) {
    }

    public function reindexClassDefinition(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged = false,
        bool $enqueueItems = false,
    ): bool {
        try {
            $mappingChanged = $this->reindexMapping($classDefinition, $skipIfClassNotChanged);
            $this->changeIcon($classDefinition);

            if ($mappingChanged && $enqueueItems) {
                $this->enqueueService->enqueueByClassDefinition($classDefinition);
            }

            return $mappingChanged;
        } catch (Exception $exception) {
            throw new ClassDefinitionIndexUpdateFailedException(
                message: $exception->getMessage(),
                previous: $exception
            );
        }
    }

    private function changeIcon(ClassDefinition $classDefinition): void
    {
        $this->indexIconUpdateService->updateIcon(
            $this->dataObjectIndexHandler->getCurrentFullIndexName($classDefinition),
            $classDefinition->getIcon() ?: null
        );
    }

    /**
     * @throws Exception
     */
    private function reindexMapping(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged
    ): bool {
        $mappingProperties = $this->dataObjectIndexHandler->getMappingProperties($classDefinition);
        $currentCheckSum = $this->dataObjectIndexHandler->getClassMappingCheckSum($mappingProperties);
        $storedCheckSum = $this->settingsStoreService->getClassMappingCheckSum($classDefinition->getId());

        if ($skipIfClassNotChanged && $storedCheckSum === $currentCheckSum) {
            return false;
        }

        $this->dataObjectIndexHandler
            ->reindexMapping(
                context: $classDefinition,
                mappingProperties: $mappingProperties
            );

        $this->settingsStoreService->storeClassMapping(
            classDefinitionId: $classDefinition->getId(),
            data: $this->dataObjectIndexHandler->getClassMappingCheckSum(
                $mappingProperties
            )
        );

        return true;
    }
}
