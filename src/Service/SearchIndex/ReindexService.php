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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexRebuild;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexRebuildReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexRebuildObligationServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Listing;

/**
 * @internal
 */
final readonly class ReindexService implements ReindexServiceInterface
{
    private const LABEL_ASSETS = 'assets';

    private const LABEL_DOCUMENTS = 'documents';

    public function __construct(
        private AssetIndexHandler $assetIndexHandler,
        private DocumentIndexHandler $documentIndexHandler,
        private EnqueueServiceInterface $enqueueService,
        private ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
        private IndexStateServiceInterface $indexStateService,
        private IndexRebuildObligationServiceInterface $rebuildObligationService,
    ) {

    }

    /**
     * @throws Exception
     */
    public function reindexAll(): ReindexService
    {
        $this
            ->reindexClassDefinitions()
            ->reindexAssets()
            ->reindexDocuments();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexAllIndices(): ReindexService
    {
        $this->reindexAllIndicesWithReport();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexAllIndicesWithReport(): IndexRebuildReport
    {
        $rebuilds = [];
        foreach ((new Listing())->load() as $classDefinition) {
            $result = $this->applyClassDefinition($classDefinition, false);
            if ($result?->requiresRebuild() === true) {
                $rebuilds[] = new IndexRebuild($classDefinition->getName(), $result);
            }
        }

        $assets = $this->applyAssets(false);
        if ($assets->requiresRebuild()) {
            $rebuilds[] = new IndexRebuild(self::LABEL_ASSETS, $assets);
        }

        $documents = $this->applyDocuments(false);
        if ($documents->requiresRebuild()) {
            $rebuilds[] = new IndexRebuild(self::LABEL_DOCUMENTS, $documents);
        }

        return new IndexRebuildReport($rebuilds);
    }

    /**
     * @throws Exception
     */
    public function reindexClassDefinitions(bool $enqueueElements = true): ReindexService
    {
        foreach ((new Listing())->load() as $classDefinition) {
            $this->applyClassDefinition($classDefinition, $enqueueElements);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexClassDefinition(
        ClassDefinition $classDefinition,
        bool $enqueueElements = true
    ): ReindexService {
        $this->applyClassDefinition($classDefinition, $enqueueElements);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexAssets(bool $enqueueElements = true): ReindexService
    {
        $this->applyAssets($enqueueElements);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexDocuments(bool $enqueueElements = true): ReindexService
    {
        $this->applyDocuments($enqueueElements);

        return $this;
    }

    /**
     * The class reindex queues the objects itself when fields were left out of the copy.
     *
     * @throws Exception
     */
    private function applyClassDefinition(
        ClassDefinition $classDefinition,
        bool $enqueueElements
    ): ?MappingUpdateResult {
        $result = $this->classDefinitionReindexService->reindexClassDefinitionMapping(
            $classDefinition,
            false,
            $enqueueElements
        );
        $rebuilt = $enqueueElements || $result?->requiresRebuild() === true;

        $this->indexStateService->refresh(
            IndexStateTarget::forClassDefinition($classDefinition),
            true,
            true,
            $rebuilt
        );

        return $result;
    }

    /**
     * @throws Exception
     */
    private function applyAssets(bool $enqueueElements): MappingUpdateResult
    {
        return $this->applyIndex(
            IndexStateTarget::forScope(DeploymentReindexScope::ASSETS),
            $this->assetIndexHandler,
            $enqueueElements,
            fn () => $this->enqueueService->enqueueAssets()
        );
    }

    /**
     * @throws Exception
     */
    private function applyDocuments(bool $enqueueElements): MappingUpdateResult
    {
        return $this->applyIndex(
            IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS),
            $this->documentIndexHandler,
            $enqueueElements,
            fn () => $this->enqueueService->enqueueDocuments()
        );
    }

    /**
     * @param callable(): mixed $enqueue queues every element of the index
     *
     * @throws Exception
     */
    private function applyIndex(
        IndexStateTarget $target,
        IndexHandlerInterface $handler,
        bool $enqueueElements,
        callable $enqueue
    ): MappingUpdateResult {
        // An earlier run that changed the index and failed before queuing its rebuild left the obligation; this
        // run's copy then changes nothing that would queue it again.
        $rebuildOwed = $this->rebuildObligationService->isOwed($target);
        $this->rebuildObligationService->owe($target);
        $result = $handler->reindexMappingWithResult();
        if ($rebuildOwed) {
            $result = $result->withRebuildOwed();
        }

        $rebuilt = $enqueueElements || $result->requiresRebuild();
        if ($rebuilt) {
            $enqueue();
        }
        $this->rebuildObligationService->settle($target);

        // Without the rebuild, fields added to the mapping stay empty, so the mapping is not current yet.
        $this->indexStateService->refresh($target, $rebuilt, true, $rebuilt);

        return $result;
    }
}
