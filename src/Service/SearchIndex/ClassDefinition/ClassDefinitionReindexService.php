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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $pimcoreGenericDataIndexLogger,
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
            // Same structured keys as the reindex branch below (here they are equal by definition),
            // so a log query on storedChecksum/currentChecksum includes skipped classes too.
            $this->pimcoreGenericDataIndexLogger->debug('Mapping unchanged, skipping reindex', [
                'class' => $classDefinition->getName(),
                'classId' => $classDefinition->getId(),
                'storedChecksum' => $storedCheckSum,
                'currentChecksum' => $currentCheckSum,
            ]);

            return false;
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
