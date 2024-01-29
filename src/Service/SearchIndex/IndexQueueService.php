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
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\BulkOperationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\PathService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use UnhandledMatchError;

class IndexQueueService
{
    use LoggerAwareTrait;

    protected bool $performIndexRefresh = false;

    public function __construct(
        private readonly IndexService $indexService,
        private readonly PathService $pathService,
        private readonly BulkOperationService $bulkOperationService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly EnqueueService $enqueueService,
    ) {
    }

    public function updateIndexQueue(
        ElementInterface $element,
        string $operation,
        bool $doIndexElement = false
    ): IndexQueueService {
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
            $this->logger->warning(
                sprintf(
                    'Update indexQueue in database-table %s failed! Error: %s',
                    IndexQueue::TABLE,
                    $e->getMessage()
                )
            );
        }

        return $this;
    }

    /**
     * @param IndexQueue[] $entries
     *
     * @throws ExceptionInterface
     */
    public function handleIndexQueueEntries(array $entries): IndexQueueService
    {
        try {

            foreach ($entries as $entry) {
                $this->logger->debug(
                    sprintf(
                        '%s updating index for element %s and type %s',
                        IndexQueue::TABLE,
                        $entry->getElementId(),
                        $entry->getElementType()
                    ));
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
     * @param ElementInterface $element
     * @param string $operation
     *
     * @return $this
     *
     * @throws JsonException
     * @throws ExceptionInterface
     */
    protected function doHandleIndexData(ElementInterface $element, string $operation): IndexQueueService
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
    protected function checkOperationValid(string $operation): void
    {
        if(!in_array($operation, [
            IndexQueueOperation::UPDATE->value,
            IndexQueueOperation::DELETE->value,
        ], true)) {
            throw new InvalidArgumentException(sprintf('Operation %s not valid', $operation));
        }
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
