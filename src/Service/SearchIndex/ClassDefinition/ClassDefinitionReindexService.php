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
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLockInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexRebuildObligationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
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
        private IndexRebuildObligationServiceInterface $rebuildObligationService,
        private IndexLockInterface $indexLock,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
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
            // The checksum is compared and stored under the lock of the index. A second update of the same class
            // (a redelivered message, or a second save within the copy time) waits for the first one and then finds
            // the mapping current, so it skips the copy instead of copying the index again.
            return $this->indexLock->run(
                $this->getAliasIndexName($classDefinition),
                fn () => $this->updateMapping($classDefinition, $skipIfClassNotChanged, $enqueueItems)
            );
        } catch (Exception $exception) {
            throw new ClassDefinitionIndexUpdateFailedException(
                message: $exception->getMessage(),
                previous: $exception
            );
        }
    }

    /**
     * @throws Exception
     */
    private function updateMapping(
        ClassDefinition $classDefinition,
        bool $skipIfClassNotChanged,
        bool $enqueueItems
    ): ?MappingUpdateResult {
        $target = IndexStateTarget::forClassDefinition($classDefinition);
        $rebuildOwed = $this->rebuildObligationService->isOwed($target);
        $result = $this->reindexMapping($classDefinition, $skipIfClassNotChanged);
        if ($rebuildOwed) {
            // An earlier run changed the index and failed before queuing its rebuild. The mapping is
            // current now, so this run's copy (if any) reports nothing that would queue it again.
            $result = $result === null ? MappingUpdateResult::forOwedRebuildOnly() : $result->withRebuildOwed();
        }
        if ($result !== null) {
            // Queuing the objects of a large class takes a while; the lock is renewed first, so that the checksum
            // is still stored under it.
            $this->indexLock->refresh($this->getAliasIndexName($classDefinition));
            // Fields left out of the copy (or an index created empty) are only restored by the
            // rebuild from the database, so the objects are queued even if the caller did not ask.
            // Queued before the checksum is stored and before the icon update: if either fails, the
            // stored checksum would let every retry skip the class, and nothing would queue it again.
            if ($enqueueItems || $result->requiresRebuild()) {
                $this->enqueueService->enqueueByClassDefinition($classDefinition);
            }
            // Renewed again before the state is written: a lock lost while queuing (another update took it over)
            // stops here, so the newer update's checksum and obligation are not overwritten.
            $this->indexLock->refresh($this->getAliasIndexName($classDefinition));
            $this->rebuildObligationService->settle($target);
            $this->storeMappingCheckSum($classDefinition);
        }
        $this->changeIcon($classDefinition);

        return $result;
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

        // Recorded before the live index changes; settled once the rebuild the update may need is queued.
        $this->rebuildObligationService->owe(IndexStateTarget::forClassDefinition($classDefinition));

        return $this->dataObjectIndexHandler
            ->reindexMappingWithResult(
                context: $classDefinition,
                mappingProperties: $mappingProperties
            );
    }

    private function getAliasIndexName(ClassDefinition $classDefinition): string
    {
        return $this->searchIndexConfigService->getIndexName($classDefinition->getName(), true);
    }

    /**
     * @throws JsonException
     */
    private function storeMappingCheckSum(ClassDefinition $classDefinition): void
    {
        $this->settingsStoreService->storeClassMapping(
            classDefinitionId: $classDefinition->getId(),
            data: $this->dataObjectIndexHandler->getClassMappingCheckSum(
                $this->dataObjectIndexHandler->getMappingProperties($classDefinition)
            )
        );
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
