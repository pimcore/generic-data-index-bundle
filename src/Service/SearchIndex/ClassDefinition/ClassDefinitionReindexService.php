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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition;

use Exception;
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final readonly class ClassDefinitionReindexService implements ClassDefinitionReindexServiceInterface
{
    public function __construct(
        private IndexHandlerInterface $dataObjectIndexHandler,
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

        if ($skipIfClassNotChanged && $storedCheckSum !== null) {
            if ($storedCheckSum === $currentCheckSum) {
                return false;
            }

            if ($storedCheckSum === $this->getLegacyClassMappingCheckSum($mappingProperties)) {
                // The mapping is unchanged, only the checksum algorithm is: checksums
                // stored before the key normalization was introduced depend on the key
                // order. Re-stamp the entry instead of reindexing the class definition.
                $this->settingsStoreService->storeClassMapping(
                    classDefinitionId: $classDefinition->getId(),
                    data: $currentCheckSum
                );

                return false;
            }
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

    /**
     * Checksum as it was calculated before the mapping properties were normalized,
     * i.e. depending on the array key order. Only used to recognise entries stored
     * by an older bundle version; can be dropped once upgrades from those versions
     * are no longer supported.
     *
     * @throws JsonException
     */
    private function getLegacyClassMappingCheckSum(array $mappingProperties): int
    {
        return crc32(json_encode($mappingProperties, JSON_THROW_ON_ERROR));
    }
}
