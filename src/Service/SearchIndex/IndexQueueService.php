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

use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Message\IndexUpdateQueueMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\AbstractIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\AssetIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\DataObjectIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\BulkOperationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Tag;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class IndexQueueService
{
    use LoggerAwareTrait;

    protected bool $performIndexRefresh = false;

    public function __construct(
        private Connection $connection,
        private AssetIndexService $assetIndexService,
        private DataObjectIndexService $dataObjectIndexService,
        private SearchIndexConfigService $searchIndexConfigService,
        private OpenSearchService $openSearchService,
        private BulkOperationService $bulkOperationService,
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
    ) {
    }

    public function updateIndexQueue(ElementInterface $element, string $operation, bool $doIndexElement = false): self
    {
        try {
            $this->checkOperationValid($operation);

            $oldFullPath = $element instanceof Asset\Folder ? $this->getCurrentIndexFullPath($element) : null;

            if ($doIndexElement) {
                $this->doHandleIndexData($element, $operation);
            }

            $elementType = $this->getElementType($element);

            $currentQueueTableOperationTime = $this->getCurrentQueueTableOperationTime();

            if ($element instanceof AbstractObject) {
                $tableName = 'objects';
                $or = $doIndexElement ? '' : sprintf('%s = %s OR', Service::getVersionDependentDatabaseColumnName('o_id'), $this->connection->quote($element->getId()));
                $sql = "SELECT %s, %s, %s, %s, %s FROM %s WHERE (%s %s LIKE %s) and %s != 'folder'";
                $selectQuery = sprintf($sql,
                    Service::getVersionDependentDatabaseColumnName('o_id'),
                    Service::getVersionDependentDatabaseColumnName('o_className'),
                    $this->connection->quote($elementType),
                    $this->connection->quote($operation),
                    $this->connection->quote($currentQueueTableOperationTime),
                    $this->connection->quoteIdentifier($tableName),
                    $or,
                    Service::getVersionDependentDatabaseColumnName('o_className'),
                    $this->connection->quote($element->getRealFullPath() . '/%'),
                    Service::getVersionDependentDatabaseColumnName('o_type')
                );
            } else {
                $tableName = 'assets';
                $or = $doIndexElement ? '' : sprintf('id = %s OR', $this->connection->quote($element->getId()));
                $sql = 'SELECT id, %s, %s, %s, %s FROM %s WHERE %s path LIKE %s';
                $selectQuery = sprintf($sql,
                    $this->connection->quote($elementType),
                    $this->connection->quote($this->getElementIndexName($element)),
                    $this->connection->quote($operation),
                    $this->connection->quote($currentQueueTableOperationTime),
                    $this->connection->quoteIdentifier($tableName),
                    $or,
                    $this->connection->quote($element->getRealFullPath() . '/%')
                );
            }

            if (!$doIndexElement || !($element instanceof Asset) || $element instanceof Asset\Folder) {
                $this->connection->executeQuery(sprintf('INSERT INTO %s (%s) %s ON DUPLICATE KEY UPDATE operation = VALUES(operation), operationTime = VALUES(operationTime)',
                    IndexQueue::TABLE,
                    implode(',', ['elementId', 'elementType', 'elementIndexName', 'operation', 'operationTime']),
                    $selectQuery
                ));
            }

            if ($element instanceof Asset) {
                $this->updateAssetDependencies($element);
            }

            if ($element instanceof Asset\Folder && !empty($oldFullPath) && $oldFullPath !== $element->getRealFullPath()) {
                $this->rewriteChildrenIndexPaths($element, $oldFullPath);
            }
        } catch (Exception $e) {
            $this->logger->warning('Update indexQueue in database-table' . IndexQueue::TABLE . ' failed! Error: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * @param bool $dispatch
     * @param int $limit
     *
     * @return array
     */
    public function getUnhandledIndexQueueEntries(bool $dispatch = false, int $limit = 100000)
    {
        $unhandledIndexQueueEntries = [];

        try {
            if ($dispatch === true) {
                $dispatchId = time();
                $workerId = uniqid();

                $this->connection->executeQuery('UPDATE ' . IndexQueue::TABLE . ' SET dispatched = ?, workerId = ? WHERE (ISNULL(dispatched) OR dispatched < ?) LIMIT ' . intval($limit),
                    [$dispatchId, $workerId, $dispatchId - 3000]);

                $unhandledIndexQueueEntries = $this->connection->executeQuery('SELECT elementId, elementType, elementIndexName, operation, operationTime FROM ' . IndexQueue::TABLE . ' WHERE workerId = ? LIMIT ' . intval($limit), [$workerId])->fetchAllAssociative();
            } else {
                $unhandledIndexQueueEntries = $this->connection->executeQuery('SELECT elementId, elementType, elementIndexName, operation, operationTime FROM ' . IndexQueue::TABLE . ' ORDER BY operationTime LIMIT ' . intval($limit))->fetchAllAssociative();
            }
        } catch (Exception $e) {
            $this->logger->info('getUnhandledIndexQueueEntries failed! Error: ' . $e->getMessage());
        }

        return $unhandledIndexQueueEntries;
    }

    public function handleIndexQueueEntry(IndexQueue $entry): IndexQueueService
    {
        return $this->handleIndexQueueEntries([$entry]);
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
            $this->deleteQueueEntries($entries);

        } catch (Exception $e) {
            $this->logger->info('handleIndexQueueEntry failed! Error: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * @param IndexQueue[] $entries
     */
    private function deleteQueueEntries(array $entries): void
    {
        foreach(array_chunk($entries, 500) as $chunk) {
            $condition = [];

            /** @var IndexQueue $entry */
            foreach($chunk as $entry) {
                $condition[] = sprintf(
                    '(elementId = %s AND elementType = %s and operationTime = %s)',
                    $this->connection->quote($entry->getElementId()),
                    $this->connection->quote($entry->getElementType()),
                    $this->connection->quote($entry->getOperationTime())
                );
            }

            $condition = '(' . implode(' OR ', $condition) . ')';

            //delete handled entry from queue table
            $this->connection->executeQuery('DELETE FROM ' . IndexQueue::TABLE . ' WHERE ' . $condition);
        }
    }

    public function denormalizeDatabaseEntry(array $entry): IndexQueue
    {
        return $this->serializer->denormalize($entry, IndexQueue::class);
    }

    /**
     * @param ClassDefinition $classDefinition
     *
     * @return $this
     */
    public function updateDataObjects($classDefinition)
    {
        $dataObjectTableName = 'object_' . $classDefinition->getId();

        $selectQuery = sprintf("SELECT oo_id, '%s', '%s', '%s', '%s' FROM %s",
            ElementType::DATA_OBJECT->value,
            $classDefinition->getName(),
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            $dataObjectTableName
        );

        $this->updateBySelectQuery($selectQuery);

        return $this;
    }

    /**
     * @return $this
     */
    public function updateAssets()
    {
        $selectQuery = sprintf("SELECT id, '%s', '%s', '%s', '%s' FROM %s",
            ElementType::ASSET->value,
            'asset',
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            'assets'
        );
        $this->updateBySelectQuery($selectQuery);

        return $this;
    }

    /**
     * @return $this
     */
    public function updateByTag(Tag $tag)
    {
        //assets
        $selectQuery = sprintf("SELECT id, '%s', '%s', '%s', '%s' FROM assets where id in (select cid from tags_assignment where ctype='asset' and tagid = %s)",
            ElementType::ASSET->value,
            'asset',
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            $tag->getId()
        );
        $this->updateBySelectQuery($selectQuery);

        //data objects
        $selectQuery = sprintf("SELECT '%s', '%s', '%s', '%s', '%s' FROM objects where %s in (select cid from tags_assignment where ctype='object' and tagid = %s)",
            Service::getVersionDependentDatabaseColumnName('o_id'),
            Service::getVersionDependentDatabaseColumnName('o_className'),
            ElementType::DATA_OBJECT->value,
            IndexQueueOperation::UPDATE->value,
            $this->getCurrentQueueTableOperationTime(),
            Service::getVersionDependentDatabaseColumnName('o_id'),
            $tag->getId()
        );
        $this->updateBySelectQuery($selectQuery);

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function getCurrentIndexFullPath(ElementInterface $element): ?string
    {
        $indexService = $this->getIndexServiceByElement($element);
        $indexName = $this->searchIndexConfigService->getIndexName($this->getElementIndexName($element));

        return $indexService->getCurrentIndexFullPath($element, $indexName);
    }

    /**
     * Directly update children paths in OpenSearch for assets as otherwise you might get strange results if you rename a folder in the portal engine frontend.
     *
     * @param ElementInterface $element
     * @param string $oldFullPath
     *
     * @throws Exception
     */
    protected function rewriteChildrenIndexPaths(ElementInterface $element, string $oldFullPath)
    {
        if ($element instanceof Asset) {
            $indexService = $this->getIndexServiceByElement($element);
            $indexName = $this->searchIndexConfigService->getIndexName($this->getElementIndexName($element));
            $indexService->rewriteChildrenIndexPaths($element, $indexName, $oldFullPath);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function updateBySelectQuery(string $selectQuery)
    {
        $this->connection->executeQuery(sprintf('INSERT INTO %s (%s) %s ON DUPLICATE KEY UPDATE operation = VALUES(operation), operationTime = VALUES(operationTime)',
            IndexQueue::TABLE,
            implode(',', ['elementId', 'elementType', 'elementIndexName', 'operation', 'operationTime']),
            $selectQuery
        ));
    }

    /**
     * @param ElementInterface $element
     *
     * @return $this
     */
    public function refreshIndexByElement(ElementInterface $element)
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

    /**
     * @param Asset $asset
     *
     * @return $this
     */
    protected function updateAssetDependencies(Asset $asset)
    {
        foreach ($asset->getDependencies()->getRequiredBy() as $requiredByEntry) {

            /** @var ElementInterface|null $element */
            $element = null;

            if ('object' === $requiredByEntry['type']) {
                $element = AbstractObject::getById($requiredByEntry['id']);
            }
            if ('asset' === $requiredByEntry['type']) {
                $element = Asset::getById($requiredByEntry['id']);
            }
            if ($element) {
                $this->updateIndexQueue($element, IndexQueueOperation::UPDATE->value);
            }
        }

        return $this;
    }

    /**
     * @param ElementInterface $element
     * @param string $operation
     *
     * @return $this
     *
     * @throws Exception
     */
    protected function doHandleIndexData(ElementInterface $element, string $operation)
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
     * @return AbstractIndexService|AssetIndexService|DataObjectIndexService
     *
     * @throws Exception
     */
    protected function getIndexServiceByElement(ElementInterface $element)
    {
        return $this->getIndexServiceByElementType($this->getElementType($element));
    }

    /**
     * @return AbstractIndexService|AssetIndexService|DataObjectIndexService
     *
     * @throws Exception
     */
    protected function getIndexServiceByElementType(string $elementType)
    {
        switch ($elementType) {
            case $elementType === ElementType::DATA_OBJECT->value:
                return $this->dataObjectIndexService;
            case $elementType === ElementType::ASSET->value:
                return $this->assetIndexService;
        }

        throw new Exception('Index service for element type ' . $elementType . ' does not exist.');
    }

    /**
     * @param ElementInterface $element
     *
     * @return $this
     */
    protected function doUpdateIndexData(ElementInterface $element)
    {
        $this
            ->getIndexServiceByElement($element)
            ->doUpdateIndexData($element);

        return $this;
    }

    /**
     * @param ElementInterface $element
     *
     * @return $this
     *
     * @throws Exception
     */
    protected function doDeleteFromIndex(ElementInterface $element)
    {
        $elementId = $element->getId();
        $elementIndexName = $this->getElementIndexName($element);

        $this
            ->getIndexServiceByElement($element)
            ->doDeleteFromIndex($elementId, $elementIndexName);

        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function checkOperationValid(string $operation)
    {
        if(!in_array($operation, [
            IndexQueueOperation::UPDATE->value,
            IndexQueueOperation::DELETE->value,
        ])) {
            throw new \InvalidArgumentException(sprintf('Operation %s not valid', $operation));
        }
    }

    /**
     * Get current timestamp + milliseconds
     *
     * @return int
     */
    protected function getCurrentQueueTableOperationTime()
    {
        $carbonNow = Carbon::now();

        return (int)($carbonNow->getTimestamp() . str_pad((string)$carbonNow->milli, 3, '0'));
    }

    /**
     * @param int $id
     * @param string $type
     *
     * @return Asset|AbstractObject|null
     *
     * @throws Exception
     */
    protected function getElement($id, $type)
    {
        switch ($type) {
            case ElementType::ASSET->value:
                return Asset::getById($id);
            case ElementType::DATA_OBJECT->value:
                return AbstractObject::getById($id);
            default:
                throw new Exception('elementType ' . $type . ' not supported');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function getElementType(ElementInterface $element): string
    {
        switch ($element) {
            case $element instanceof AbstractObject:
                return ElementType::DATA_OBJECT->value;
            case $element instanceof Asset:
                return ElementType::ASSET->value;
            default:
                throw new \InvalidArgumentException('element ' . get_class($element) . ' not supported');
        }
    }

    /**
     * @throws Exception
     */
    protected function getElementIndexName(ElementInterface $element): string
    {
        switch ($element) {
            case $element instanceof Concrete:
                return $element->getClassName();
            case $element instanceof Asset:
                return 'asset';
            default:
                throw new Exception('element ' . get_class($element) . ' not supported');
        }
    }

    /**
     * @return bool
     */
    public function isPerformIndexRefresh(): bool
    {
        return $this->performIndexRefresh;
    }

    public function setPerformIndexRefresh(bool $performIndexRefresh): IndexQueueService
    {
        $this->performIndexRefresh = $performIndexRefresh;

        return $this;
    }

    public function countQueuedItems(): int
    {
        try {
            return $this->connection->fetchOne(
                sprintf('SELECT count(*) as count FROM %s', IndexQueue::TABLE)
            ) ?? 0;
        } catch (TableNotFoundException $exception) {
            return 0;
        }
    }

    public function dispatchQueueMessages(OutputInterface $output)
    {
        $entries = $this->getUnhandledIndexQueueEntries();

        $progressBar = new ProgressBar($output, count($entries));
        $progressBar->start();

        foreach(array_chunk($entries, 400) as $entriesBatch) {
            $message = new IndexUpdateQueueMessage($entries, uniqid());
            $this->messageBus->dispatch($message);
            $progressBar->advance(sizeof($entriesBatch));
        }

        $progressBar->finish();
    }
}
