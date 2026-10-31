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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Listing;

/**
 * @internal
 */
final readonly class ReindexService implements ReindexServiceInterface
{
    public function __construct(
        private AssetIndexHandler $assetIndexHandler,
        private DocumentIndexHandler $documentIndexHandler,
        private EnqueueServiceInterface $enqueueService,
        private ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
        private IndexStateServiceInterface $indexStateService,
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
        $this
            ->reindexClassDefinitions(false)
            ->reindexAssets(false)
            ->reindexDocuments(false);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexClassDefinitions(bool $enqueueElements = true): ReindexService
    {
        foreach ((new Listing())->load() as $classDefinition) {
            $this->reindexClassDefinition($classDefinition, $enqueueElements);
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
        $this->classDefinitionReindexService->reindexClassDefinition(
            $classDefinition,
            false,
            $enqueueElements
        );

        $this->indexStateService->refresh(
            IndexStateTarget::forClassDefinition($classDefinition),
            true,
            true,
            $enqueueElements
        );

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexAssets(bool $enqueueElements = true): ReindexService
    {
        $this->assetIndexHandler->reindexMapping();

        if ($enqueueElements) {
            $this
                ->enqueueService
                ->enqueueAssets();
        }

        // Without the rebuild, fields added to the mapping stay empty, so the mapping is not current yet.
        $this->indexStateService->refresh(
            IndexStateTarget::forScope(DeploymentReindexScope::ASSETS),
            $enqueueElements,
            true,
            $enqueueElements
        );

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexDocuments(bool $enqueueElements = true): ReindexService
    {
        $this->documentIndexHandler->reindexMapping();

        if ($enqueueElements) {
            $this
                ->enqueueService
                ->enqueueDocuments();
        }

        // Without the rebuild, fields added to the mapping stay empty, so the mapping is not current yet.
        $this->indexStateService->refresh(
            IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS),
            $enqueueElements,
            true,
            $enqueueElements
        );

        return $this;
    }
}
