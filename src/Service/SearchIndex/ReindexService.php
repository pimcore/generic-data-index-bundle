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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionIndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
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
        private DataObjectIndexHandler $dataObjectIndexHandler,
        private EnqueueServiceInterface $enqueueService,
        private SettingsStoreServiceInterface $settingsStoreService,
        private ClassDefinitionIndexUpdateServiceInterface $classDefinitionIndexUpdateService,
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
        $this->classDefinitionIndexUpdateService->reindexClassDefinition(
            $classDefinition,
            false,
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

        return $this;
    }
}
