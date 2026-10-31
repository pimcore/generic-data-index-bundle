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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexPlan;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final readonly class DeploymentReindexService implements DeploymentReindexServiceInterface
{
    public function __construct(
        private IndexStateServiceInterface $indexStateService,
        private ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
        private EnqueueServiceInterface $enqueueService,
        private IndexHandlerInterface $assetIndexHandler,
        private IndexHandlerInterface $documentIndexHandler,
        private IndexHandlerInterface $dataObjectIndexHandler,
    ) {
    }

    public function getTargets(array $scopes): array
    {
        $targets = [];
        foreach (DeploymentReindexScope::cases() as $scope) {
            if (!in_array($scope, $scopes, true)) {
                continue;
            }

            if ($scope !== DeploymentReindexScope::CLASSES) {
                $targets[] = IndexStateTarget::forScope($scope);

                continue;
            }

            foreach ((new ClassDefinition\Listing())->load() as $classDefinition) {
                $targets[] = IndexStateTarget::forClassDefinition($classDefinition);
            }
        }

        return $targets;
    }

    public function plan(IndexStateTarget $target): IndexPlan
    {
        $current = $this->indexStateService->getCurrentState($target);
        $stored = $this->indexStateService->getStoredState($target);
        $indexMissing = !$this->indexStateService->indexExists($target);
        $classDefinition = $target->getClassDefinition();
        $reasons = [];

        if ($indexMissing) {
            $reasons[] = 'index missing';
        }

        if ($classDefinition !== null) {
            // Same decision as without options, including the reindex of classes without a checksum.
            $mappingChanged = $this->classDefinitionReindexService->isMappingChanged($classDefinition);
        } else {
            $mappingChanged = $stored->getMappingChecksum() !== null
                && $stored->getMappingChecksum() !== $current->getMappingChecksum();
        }
        if ($mappingChanged) {
            $reasons[] = $stored->getMappingChecksum() === IndexStateServiceInterface::INVALIDATED
                ? 'previous snapshot import did not finish'
                : 'mapping changed';
        }

        $settingsChanged = $stored->getSettingsHash() !== null
            && $stored->getSettingsHash() !== $current->getSettingsHash();
        if ($settingsChanged) {
            $reasons[] = 'index settings changed';
        }

        $dataVersionChanged = $stored->getDataVersion() !== null
            && $stored->getDataVersion() !== $current->getDataVersion();
        if ($dataVersionChanged) {
            $reasons[] = sprintf(
                'data version changed (%s -> %s)',
                $stored->getDataVersion(),
                $current->getDataVersion()
            );
        }

        $baselineMissing = $stored->getSettingsHash() === null
            || $stored->getDataVersion() === null
            || ($classDefinition === null && $stored->getMappingChecksum() === null);
        if ($baselineMissing) {
            $reasons[] = 'no stored state yet';
        }

        return new IndexPlan(
            $target,
            $current,
            $indexMissing,
            $mappingChanged,
            $settingsChanged,
            $dataVersionChanged,
            $baselineMissing,
            $reasons
        );
    }

    public function execute(IndexPlan $plan): bool
    {
        $target = $plan->getTarget();
        $classDefinition = $target->getClassDefinition();

        if ($classDefinition !== null) {
            // Runs for unchanged classes too, like the run without options (icon update).
            $enqueued = $this->executeForClassDefinition($plan, $classDefinition);
        } elseif ($plan->isSkipped()) {
            return false;
        } else {
            $enqueued = $this->executeForIndex($plan);
        }

        if (!$plan->isSkipped()) {
            $this->indexStateService->store($target, $plan->getCurrentState());
        }

        return $enqueued;
    }

    private function executeForClassDefinition(IndexPlan $plan, ClassDefinition $classDefinition): bool
    {
        // Keeps the checksum handling of the run without options: legacy checksums, icon update,
        // reindex and enqueue of changed classes.
        $reindexed = $this->classDefinitionReindexService->reindexClassDefinition(
            $classDefinition,
            !$plan->isIndexMissing(),
            true
        );
        if ($reindexed) {
            return true;
        }

        if ($plan->isSettingsChanged()) {
            $this->dataObjectIndexHandler->reindexMapping($classDefinition);
        }

        if ($plan->isDataVersionChanged()) {
            $this->enqueueService->enqueueByClassDefinition($classDefinition);

            return true;
        }

        return false;
    }

    private function executeForIndex(IndexPlan $plan): bool
    {
        $handler = $this->getHandler($plan->getTarget());

        if ($plan->isIndexMissing()) {
            $handler->updateMapping();
        } elseif ($plan->needsNativeReindex()) {
            $handler->reindexMapping();
        }

        if (!$plan->needsRebuild()) {
            return false;
        }

        match ($plan->getTarget()->getScope()) {
            DeploymentReindexScope::ASSETS => $this->enqueueService->enqueueAssets(),
            DeploymentReindexScope::DOCUMENTS => $this->enqueueService->enqueueDocuments(),
            DeploymentReindexScope::FOLDERS => $this->enqueueService->enqueueDataObjectFolders(),
            DeploymentReindexScope::CLASSES => throw new \LogicException('Class indices are handled separately'),
        };

        return true;
    }

    private function getHandler(IndexStateTarget $target): IndexHandlerInterface
    {
        return match ($target->getScope()) {
            DeploymentReindexScope::ASSETS => $this->assetIndexHandler,
            DeploymentReindexScope::DOCUMENTS => $this->documentIndexHandler,
            DeploymentReindexScope::CLASSES, DeploymentReindexScope::FOLDERS => $this->dataObjectIndexHandler,
        };
    }
}
