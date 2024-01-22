<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\AbstractIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\AssetIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\DataObjectIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\BulkOperationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\PathService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Tag;
use UnhandledMatchError;

class IndexQueueService
{
    use LoggerAwareTrait;

    protected bool $performIndexRefresh = false;

    public function __construct(
        private readonly AssetIndexService $assetIndexService,
        private readonly DataObjectIndexService $dataObjectIndexService,
        private readonly SearchIndexConfigService $searchIndexConfigService,
        private readonly OpenSearchService $openSearchService,
        private readonly PathService $pathService,
        private readonly BulkOperationService $bulkOperationService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
        private readonly TimeService $timeService,
        private readonly IndexQueueRepository $indexQueueRepository,
    ) {
    }

    public function updateIndexQueue(ElementInterface $element, string $operation, bool $doIndexElement = false): IndexQueueService
    {
        try {
            $this->checkOperationValid($operation);

            $oldFullPath = $this->getCurrentIndexFullPath($element);

            if ($doIndexElement) {
                $this->doHandleIndexData($element, $operation);
            }

            $subQuery = $this->getIndexServiceByElement($element)
                ->getRelatedItemsOnUpdateQuery(
                    element: $element,
                    operation: $operation,
                    operationTime: $this->getCurrentQueueTableOperationTime(),
                    includeElement: !$doIndexElement,
                );

            if ($subQuery) {
                $this->indexQueueRepository->enqueueBySelectQuery($subQuery->getSQL(), $subQuery->getParameters());
            }

            if ($element instanceof Asset) {
                $this->updateAssetDependencies($element);
            }

            $this->rewriteChildrenIndexPaths($element, $oldFullPath);
        } catch (Exception $e) {
            $this->logger->warning('Update indexQueue in database-table' . IndexQueue::TABLE . ' failed! Error: ' . $e->getMessage());
        }

        return $this;
    }


    /**
     * @param IndexQueue[] $entries
     */
    public function handleIndexQueueEntries(array $entries): IndexQueueService
    {
        try {

            foreach ($entries as $entry) {
                $this->logger->debug(IndexQueue::TABLE . ' updating index for element ' . $entry->getElementId() . ' and type ' . $entry->getElementType());

                $element = $this->getElement($entry->getElementId(), $entry->getElementType());
                if ($element) {
                    $this->doHandleIndexData($element, $entry->getOperation());
                }
            }

            $this->bulkOperationService->commit();
            $this->indexQueueRepository->deleteQueueEntries($entries);

        } catch (Exception $e) {
            $this->logger->info('handleIndexQueueEntry failed! Error: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateDataObjects(ClassDefinition $classDefinition): IndexQueueService
    {
        $dataObjectTableName = 'object_' . $classDefinition->getId();

        $selectQuery = sprintf("SELECT oo_id, '%s', '%s', '%s', '%s', 0 FROM %s",
            ElementType::DATA_OBJECT->value,
            $classDefinition->getName(),
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            $dataObjectTableName
        );

        $this->indexQueueRepository->enqueueBySelectQuery($selectQuery);

        return $this;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateAssets(): IndexQueueService
    {
        $selectQuery = sprintf("SELECT id, '%s', '%s', '%s', '%s', 0 FROM %s",
            ElementType::ASSET->value,
            IndexName::ASSET->value,
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            'assets'
        );
        $this->indexQueueRepository->enqueueBySelectQuery($selectQuery);

        return $this;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateByTag(Tag $tag): IndexQueueService
    {
        //assets
        $selectQuery = sprintf("SELECT id, '%s', '%s', '%s', '%s', 0 FROM assets where id in (select cid from tags_assignment where ctype='asset' and tagid = %s)",
            ElementType::ASSET->value,
            IndexName::ASSET->value,
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            $tag->getId()
        );
        $this->indexQueueRepository->enqueueBySelectQuery($selectQuery);

        //data objects
        $selectQuery = sprintf("SELECT '%s', '%s', '%s', '%s', '%s', 0 FROM objects where %s in (select cid from tags_assignment where ctype='object' and tagid = %s)",
            Service::getVersionDependentDatabaseColumnName('o_id'),
            Service::getVersionDependentDatabaseColumnName('o_className'),
            ElementType::DATA_OBJECT->value,
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            Service::getVersionDependentDatabaseColumnName('o_id'),
            $tag->getId()
        );
        $this->indexQueueRepository->enqueueBySelectQuery($selectQuery);

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function getCurrentIndexFullPath(ElementInterface $element): ?string
    {
        $indexName = $this->searchIndexConfigService->getIndexName($this->getElementIndexName($element));

        return $this->pathService->getCurrentIndexFullPath($element, $indexName);
    }

    /**
     * Directly update children paths in OpenSearch for assets as otherwise you might get strange results if you rename a folder in the portal engine frontend.
     *
     * @throws Exception
     */
    protected function rewriteChildrenIndexPaths(ElementInterface $element, ?string $oldFullPath): void
    {
        if (empty($oldFullPath) || $oldFullPath === $element->getRealFullPath()) {
            return;
        }

        if ($element instanceof Asset && !$element instanceof Asset\Folder) {
            return;
        }

        $indexName = $this->searchIndexConfigService->getIndexName($this->getElementIndexName($element));
        $this
            ->getIndexServiceByElement($element)
            ->rewriteChildrenIndexPaths($element, $indexName, $oldFullPath);
    }

    public function refreshIndexByElement(ElementInterface $element): IndexQueueService
    {
        try {
            $indexName = $this->searchIndexConfigService->getIndexName(
                $this->getElementIndexName($element)
            );

            $this->openSearchService->refreshIndex($indexName);

        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
        }

        return $this;
    }

    protected function updateAssetDependencies(Asset $asset): IndexQueueService
    {
        foreach ($asset->getDependencies()->getRequiredBy() as $requiredByEntry) {

            /** @var ElementInterface|null $element */
            $element = null;

            if ($requiredByEntry['type'] === 'object') {
                $element = AbstractObject::getById($requiredByEntry['id']);
            }
            if ($requiredByEntry['type'] === 'asset') {
                $element = Asset::getById($requiredByEntry['id']);
            }
            if ($element) {
                $this->updateIndexQueue($element, IndexQueueOperation::UPDATE->value);
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function doHandleIndexData(ElementInterface $element, string $operation): IndexQueueService
    {

        $indexService = $this->getIndexServiceByElement($element);
        $indexServicePerformIndexRefreshBackup = $indexService->isPerformIndexRefresh();

        $indexService->setPerformIndexRefresh($this->performIndexRefresh);

        switch ($operation) {
            case IndexQueueOperation::UPDATE->value:
                $this->doUpdateIndexData($element);

                break;
            case IndexQueueOperation::DELETE->value:
                $this->doDeleteFromIndex($element);

                break;
        }

        $indexService->setPerformIndexRefresh($indexServicePerformIndexRefreshBackup);

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function getIndexServiceByElement(ElementInterface $element): AbstractIndexService|AssetIndexService|DataObjectIndexService
    {
        return $this->getIndexServiceByElementType($this->getElementType($element));
    }

    /**
     * @throws UnhandledMatchError
     */
    protected function getIndexServiceByElementType(string $elementType): AbstractIndexService|AssetIndexService|DataObjectIndexService
    {
        return match ($elementType) {
            ElementType::DATA_OBJECT->value => $this->dataObjectIndexService,
            ElementType::ASSET->value => $this->assetIndexService,
        };
    }

    /**
     * @throws Exception
     */
    protected function doUpdateIndexData(ElementInterface $element): IndexQueueService
    {
        $this
            ->getIndexServiceByElement($element)
            ->doUpdateIndexData($element);

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function doDeleteFromIndex(ElementInterface $element): IndexQueueService
    {
        $elementId = $element->getId();
        $elementIndexName = $this->getElementIndexName($element);

        $this
            ->getIndexServiceByElement($element)
            ->doDeleteFromIndex($elementId, $elementIndexName);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function checkOperationValid(string $operation): void
    {
        if(!in_array($operation, [
            IndexQueueOperation::UPDATE->value,
            IndexQueueOperation::DELETE->value,
        ], true)) {
            throw new InvalidArgumentException(sprintf('Operation %s not valid', $operation));
        }
    }

    protected function getCurrentQueueTableOperationTime(): int
    {
        return $this->timeService->getCurrentMillisecondTimestamp();
    }

    /**
     * @throws UnhandledMatchError
     */
    public function getElement(int $id, string $type): Asset|AbstractObject|null
    {
        return match($type) {
            ElementType::ASSET->value => Asset::getById($id),
            ElementType::DATA_OBJECT->value => AbstractObject::getById($id),
        };
    }

    /**
     * @throws UnhandledMatchError
     */
    protected function getElementType(ElementInterface $element): string
    {
        return match (true) {
            $element instanceof AbstractObject => ElementType::DATA_OBJECT->value,
            $element instanceof Asset => ElementType::ASSET->value,
        };
    }

    protected function getElementIndexName(ElementInterface $element): string
    {
        return match (true) {
            $element instanceof Concrete => $element->getClassName(),
            $element instanceof Asset => IndexName::ASSET->value,
        };
    }

    public function isPerformIndexRefresh(): bool
    {
        return $this->performIndexRefresh;
    }

    public function setPerformIndexRefresh(bool $performIndexRefresh): IndexQueueService
    {
        $this->performIndexRefresh = $performIndexRefresh;

        return $this;
    }

    public function dispatchQueueMessages(bool $synchronously = false): void
    {
        $this->queueMessagesDispatcher->dispatchQueueMessages($synchronously);
    }

    public function commit(): IndexQueueService
    {
        $this->bulkOperationService->commit();

        return $this;
    }
}
