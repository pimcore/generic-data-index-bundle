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
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $pimcoreGenericDataIndexLogger,
    ) {
    }

    public function reindexClassDefinition(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged = false,
        bool $enqueueItems = false,
    ): bool {
        return $this->reindexClassDefinitionMapping($classDefinition, $skipIfClassNotChanged, $enqueueItems) !== null;
    }

    public function reindexClassDefinitionMapping(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged = false,
        bool $enqueueItems = false,
    ): ?MappingUpdateResult {
        try {
            $result = $this->reindexMapping($classDefinition, $skipIfClassNotChanged);
            $this->changeIcon($classDefinition);

            // Fields left out of the copy (or an index created empty) are only restored by the
            // rebuild from the database, so the objects are queued even if the caller did not ask.
            if ($result !== null && ($enqueueItems || $result->requiresRebuild())) {
                $this->enqueueService->enqueueByClassDefinition($classDefinition);
            }

            return $result;
        } catch (Exception $exception) {
            throw new ClassDefinitionIndexUpdateFailedException(
                message: $exception->getMessage(),
                previous: $exception
            );
        }
    }

    /**
     * @throws JsonException
     */
    public function isMappingChanged(ClassDefinition $classDefinition): bool
    {
        $mappingProperties = $this->dataObjectIndexHandler->getMappingProperties($classDefinition);
        $storedCheckSum = $this->settingsStoreService->getClassMappingCheckSum($classDefinition->getId());

        return $storedCheckSum === null
            || (
                $storedCheckSum !== $this->dataObjectIndexHandler->getClassMappingCheckSum($mappingProperties)
                && $storedCheckSum !== $this->getLegacyClassMappingCheckSum($mappingProperties)
            );
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
    ): ?MappingUpdateResult {
        $mappingProperties = $this->dataObjectIndexHandler->getMappingProperties($classDefinition);
        $currentCheckSum = $this->dataObjectIndexHandler->getClassMappingCheckSum($mappingProperties);
        $storedCheckSum = $this->settingsStoreService->getClassMappingCheckSum($classDefinition->getId());

        if ($skipIfClassNotChanged && $storedCheckSum !== null) {
            if ($storedCheckSum === $currentCheckSum) {
                // Same structured keys as the reindex branch below (here they are equal by
                // definition), so a log query on storedChecksum/currentChecksum includes skipped
                // classes too.
                $this->pimcoreGenericDataIndexLogger->debug('Mapping unchanged, skipping reindex', [
                    'class' => $classDefinition->getName(),
                    'classId' => $classDefinition->getId(),
                    'storedChecksum' => $storedCheckSum,
                    'currentChecksum' => $currentCheckSum,
                ]);

                return null;
            }

            if ($storedCheckSum === $this->getLegacyClassMappingCheckSum($mappingProperties)) {
                // The mapping is unchanged, only the checksum algorithm is: checksums
                // stored before the key normalization was introduced depend on the key
                // order. Re-stamp the entry instead of reindexing the class definition.
                $this->pimcoreGenericDataIndexLogger->debug('Mapping unchanged, re-stamping legacy checksum and skipping reindex', [
                    'class' => $classDefinition->getName(),
                    'classId' => $classDefinition->getId(),
                    'storedChecksum' => $storedCheckSum,
                    'currentChecksum' => $currentCheckSum,
                ]);

                $this->settingsStoreService->storeClassMapping(
                    classDefinitionId: $classDefinition->getId(),
                    data: $currentCheckSum
                );

                return null;
            }
        }

        // Reached both when the mapping actually changed and when a caller forces a reindex
        // ($skipIfClassNotChanged === false) despite equal checksums, so the message states the
        // decision without asserting a change - the stored vs current checksum tell that story.
        $this->pimcoreGenericDataIndexLogger->info('Reindexing class mapping', [
            'class' => $classDefinition->getName(),
            'classId' => $classDefinition->getId(),
            'storedChecksum' => $storedCheckSum,
            'currentChecksum' => $currentCheckSum,
        ]);

        $result = $this->dataObjectIndexHandler
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

        return $result;
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
