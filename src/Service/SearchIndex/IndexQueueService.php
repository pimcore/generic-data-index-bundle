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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class IndexQueueService implements IndexQueueServiceInterface
{
    use LoggerAwareTrait;

    private bool $performIndexRefresh = false;

    public function __construct(
        private readonly IndexServiceInterface $indexService,
        private readonly PathServiceInterface $pathService,
        private readonly BulkOperationServiceInterface $bulkOperationService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly EnqueueServiceInterface $enqueueService,
    ) {
    }

    public function updateIndexQueue(ElementInterface $element, string $operation, bool $doIndexElement = false): IndexQueueService
    {
        try {
            $this->checkOperationValid($operation);

            if ($doIndexElement) {
                $this->doHandleIndexData($element, $operation);
            }

            $this->enqueueService->enqueueRelatedItemsOnUpdate(
                element: $element,
                includeElement: !$doIndexElement
            );

            if ($element instanceof Asset) {
                $this->updateAssetDependencies($element);
            }

            $this->pathService->rewriteChildrenIndexPaths($element);
        } catch (Exception $e) {
            $this->logger->warning('Update indexQueue in database-table' . IndexQueue::TABLE . ' failed! Error: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * @param IndexQueue[] $entries
     *
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
            $this->logger->warning('handleIndexQueueEntry failed! Error: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * @throws InvalidElementTypeException
     */
    public function getElement(int $id, string $type): Asset|AbstractObject|null
    {
        return match($type) {
            ElementType::ASSET->value => Asset::getById($id),
            ElementType::DATA_OBJECT->value => AbstractObject::getById($id),
            default => throw new InvalidElementTypeException('Invalid element type: ' . $type)
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

    private function updateAssetDependencies(Asset $asset): IndexQueueService
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
     * @param ElementInterface $element
     * @param string $operation
     *
     * @return $this
     *
     * @throws IndexDataException
     */
    private function doHandleIndexData(ElementInterface $element, string $operation): IndexQueueService
    {
        $performIndexRefreshBackup = $this->indexService->isPerformIndexRefresh();

        $this->indexService->setPerformIndexRefresh($this->isPerformIndexRefresh());

        switch ($operation) {
            case IndexQueueOperation::UPDATE->value:
                $this->indexService->updateIndexData($element);

                break;
            case IndexQueueOperation::DELETE->value:
                $this->indexService->deleteFromIndex($element);

                break;
        }

        $this->indexService->setPerformIndexRefresh($performIndexRefreshBackup);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function checkOperationValid(string $operation): void
    {
        if(!in_array($operation, [
            IndexQueueOperation::UPDATE->value,
            IndexQueueOperation::DELETE->value,
        ], true)) {
            throw new InvalidArgumentException(sprintf('Operation %s not valid', $operation));
        }
    }
}
