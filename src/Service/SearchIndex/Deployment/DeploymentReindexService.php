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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingChangeStrategy;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexPlan;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexState;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexRebuildObligationServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final readonly class DeploymentReindexService implements DeploymentReindexServiceInterface
{
    private MappingChangeStrategy $mappingChangeStrategy;

    /**
     * @param string $mappingChangeStrategy the configured `reindex_settings.mapping_change_strategy`
     */
    public function __construct(
        private IndexStateServiceInterface $indexStateService,
        private ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
        private EnqueueServiceInterface $enqueueService,
        private IndexHandlerInterface $assetIndexHandler,
        private IndexHandlerInterface $documentIndexHandler,
        private IndexHandlerInterface $dataObjectIndexHandler,
        private IndexRebuildObligationServiceInterface $rebuildObligationService,
        string $mappingChangeStrategy = MappingChangeStrategy::RECREATE->value,
    ) {
        $this->mappingChangeStrategy = MappingChangeStrategy::from($mappingChangeStrategy);
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
            array_push($reasons, ...$this->describeMappingChange($target, $stored, $indexMissing));
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

        $rebuildOwed = $this->rebuildObligationService->isOwed($target);
        if ($rebuildOwed) {
            $reasons[] = 'rebuild owed: an earlier run changed the index and failed before queuing it';
        }

        return new IndexPlan(
            $target,
            $current,
            $indexMissing,
            $mappingChanged,
            $settingsChanged,
            $dataVersionChanged,
            $baselineMissing,
            $reasons,
            $rebuildOwed
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

    public function getMappingChangeStrategy(): MappingChangeStrategy
    {
        return $this->mappingChangeStrategy;
    }

    private function executeForClassDefinition(IndexPlan $plan, ClassDefinition $classDefinition): bool
    {
        // Keeps the checksum handling of the run without options: legacy checksums, icon update,
        // reindex and enqueue of changed classes. With the enqueue requested, a result means the
        // objects were queued.
        $result = $this->classDefinitionReindexService->reindexClassDefinitionMapping(
            $classDefinition,
            !$plan->isIndexMissing(),
            true
        );
        if ($result?->isMappingApplied() === true) {
            return true;
        }
        // Nothing applied, or only the rebuild an earlier run owed was queued: index settings that are
        // still pending must be applied all the same, or their stored hash would hide them for good.
        $enqueued = $result !== null;

        if ($plan->isSettingsChanged()) {
            // The mapping was current, but fields could still be left out of the copy (e.g. values
            // written under an older mapping); only the rebuild restores them.
            if ($this->reindexMappingOwingRebuild($plan->getTarget(), $classDefinition)->requiresRebuild()
                && !$enqueued
            ) {
                $this->enqueueService->enqueueByClassDefinition($classDefinition);
                $enqueued = true;
            }
            $this->rebuildObligationService->settle($plan->getTarget());
        }

        if ($plan->isDataVersionChanged() && !$enqueued) {
            $this->enqueueService->enqueueByClassDefinition($classDefinition);
            $enqueued = true;
        }

        return $enqueued;
    }

    private function executeForIndex(IndexPlan $plan): bool
    {
        $target = $plan->getTarget();
        $handler = $this->getHandler($target);

        $copyRequiresRebuild = false;
        if ($plan->isIndexMissing()) {
            $handler->updateMapping();
        } elseif ($plan->needsNativeReindex()) {
            $copyRequiresRebuild = $this->reindexMappingOwingRebuild($target)->requiresRebuild();
        }

        if (!$plan->needsRebuild() && !$copyRequiresRebuild) {
            $this->rebuildObligationService->settle($target);

            return false;
        }

        match ($target->getScope()) {
            DeploymentReindexScope::ASSETS => $this->enqueueService->enqueueAssets(),
            DeploymentReindexScope::DOCUMENTS => $this->enqueueService->enqueueDocuments(),
            DeploymentReindexScope::FOLDERS => $this->enqueueService->enqueueDataObjectFolders(),
            DeploymentReindexScope::CLASSES => throw new \LogicException('Class indices are handled separately'),
        };
        $this->rebuildObligationService->settle($target);

        return true;
    }

    /**
     * Applies the mapping with an obligation recorded before the live index changes: a run that fails between
     * the copy and the enqueue leaves it, and the next run queues the rebuild although its copy changes nothing.
     *
     * @throws Exception
     */
    private function reindexMappingOwingRebuild(
        IndexStateTarget $target,
        ?ClassDefinition $classDefinition = null
    ): MappingUpdateResult {
        $rebuildOwed = $this->rebuildObligationService->isOwed($target);
        $this->rebuildObligationService->owe($target);
        $result = $this->getHandler($target)->reindexMappingWithResult($classDefinition);

        return $rebuildOwed ? $result->withRebuildOwed() : $result;
    }

    /**
     * @return string[]
     */
    private function describeMappingChange(IndexStateTarget $target, IndexState $stored, bool $indexMissing): array
    {
        $reasons = [
            $stored->getMappingChecksum() === IndexStateServiceInterface::INVALIDATED
                ? 'previous snapshot import did not finish'
                : 'mapping changed',
        ];
        if ($indexMissing) {
            return $reasons;
        }

        return [...$reasons, ...$this->describeFieldChanges($target)];
    }

    /**
     * The fields the copy will keep or leave out, for the plan output (also shown by --dry-run). With the
     * "recreate" strategy nothing is left out: the changed fields are named so that the reader knows which
     * old values have to fit the new mapping, or the index is recreated empty.
     *
     * @return string[]
     */
    private function describeFieldChanges(IndexStateTarget $target): array
    {
        $diff = $this->getHandler($target)->getMappingDiff($target->getClassDefinition());
        $reasons = [];
        if ($this->mappingChangeStrategy === MappingChangeStrategy::RECREATE) {
            $changed = [...$diff->getStripPaths(), ...$diff->getKeptPaths(), ...$diff->getProtectedPaths()];
            if ($changed !== []) {
                $reasons[] = sprintf(
                    'changed fields copied as they are (mapping_change_strategy "%s"), the index is recreated '
                    . 'empty and rebuilt from the database if their values do not fit: %s',
                    $this->mappingChangeStrategy->value,
                    implode(', ', $changed)
                );
            }
            if ($diff->getStripPaths() !== []) {
                $reasons[] = sprintf(
                    'fields the "%s" strategy would leave out of the copy instead: %s',
                    MappingChangeStrategy::COPY->value,
                    implode(', ', $diff->getStripPaths())
                );
            }

            return $reasons;
        }

        if ($diff->getStripPaths() !== []) {
            $reasons[] = 'fields stripped in the copy: ' . implode(', ', $diff->getStripPaths());
        }
        if ($diff->getKeptPaths() !== []) {
            $reasons[] = 'fields kept in the copy: ' . implode(', ', $diff->getKeptPaths());
        }
        if ($diff->getProtectedPaths() !== []) {
            $reasons[] = 'fields outside class and custom fields copied as they are, '
                . 'the index is recreated if their values do not fit: '
                . implode(', ', $diff->getProtectedPaths());
        }

        return $reasons;
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
