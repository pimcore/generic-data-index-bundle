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

use Doctrine\DBAL\Connection;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\HandleIndexQueueEntriesException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Message\EnqueueRelatedIdsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * @internal
 */
final class IndexQueueService implements IndexQueueServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly IndexServiceInterface $indexService,
        private readonly PathServiceInterface $pathService,
        private readonly BulkOperationServiceInterface $bulkOperationService,
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly ElementServiceInterface $elementService,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly MessageBusInterface $messageBus,
        private readonly Connection $connection
    ) {
    }

    public function updateIndexQueue(
        ElementInterface $element,
        string $operation,
        bool $processSynchronously = false,
        bool $enqueueRelatedItems = true,
        bool $enqueueRelatedItemsAsync = false
    ): IndexQueueService {
        try {
            $this->checkOperationValid($operation);

            // Do not send to the search index synchronously when inside an open database transaction
            // for UPDATE operations. The save event fires after Pimcore's inner savepoint commits
            // but before any outer transaction the caller opened, so the DB record may still be
            // rolled back. Fall back to the queue path: the queue entry lives in the same
            // transaction and is rolled back together with the object if the caller rolls back.
            // DELETE operations are exempt: they only need the element ID (no DB read required)
            // and child deletions fire their POST_DELETE while still inside the parent's transaction,
            // so this check would prevent synchronous index cleanup of deleted children.
            if (
                $processSynchronously &&
                $operation !== IndexQueueOperation::DELETE->value &&
                $this->connection->getTransactionNestingLevel() > 0
            ) {
                $processSynchronously = false;
            }

            if ($processSynchronously) {
                $this->doHandleIndexData($element, $operation);
            }

            if ($enqueueRelatedItems || $processSynchronously === false) {
                if ($enqueueRelatedItemsAsync) {
                    $this->dispatchEnqueueRelatedIdsMessage($element, $operation, !$processSynchronously);
                } else {
                    $this->handleQueueByOperation($element, $operation, $processSynchronously);
                }
            }

            $this->pathService->rewriteChildrenIndexPaths($element);
        } catch (Exception $e) {
            $this->logger->error(
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
     */
    public function handleIndexQueueEntries(array $entries): void
    {
        $processedEntries = [];

        foreach ($entries as $entry) {
            try {
                // Field-level extraction failures (e.g. a missing physical file backing an asset's
                // thumbnail/text/duration/dimensions) are caught inside the asset-type serialization
                // handlers and degrade that single field to null; they never reach this catch. Such
                // an entry is intentionally treated as processed - a missing file will not reappear,
                // so retrying it forever would be pointless - and is indexed with the fields it could
                // extract. Only failures that abort handling entirely (e.g. an unresolvable element,
                // an index/backend error) land here; the entry stays dispatched and is picked up
                // again by dispatchItems()'s existing 24h staleness reclaim, same as before this fix
                // isolated failures to a single entry instead of aborting the whole batch.
                $this->handleEntryByOperation($entry->getOperation(), $entry);
                $processedEntries[] = $entry;
            } catch (Throwable $e) {
                $this->logger->error(
                    sprintf(
                        '%s failed to update index for element %s and type %s. Error: %s',
                        IndexQueue::TABLE,
                        $entry->getElementId(),
                        $entry->getElementType(),
                        $e->getMessage()
                    ));
            }
        }

        try {
            $this->bulkOperationService->commit();
            $this->indexQueueRepository->deleteQueueEntries($processedEntries);
        } catch (Exception $e) {
            throw new HandleIndexQueueEntriesException('handleIndexQueueEntry failed! Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function commit(?string $refreshIndex = null): IndexQueueService
    {
        $this->bulkOperationService->commit($refreshIndex);

        return $this;
    }

    /**
     * @throws IndexDataException
     */
    private function doHandleIndexData(ElementInterface $element, string $operation): void
    {
        switch ($operation) {
            case IndexQueueOperation::UPDATE->value:
                $this->indexService->updateIndexData($element);

                break;
            case IndexQueueOperation::DELETE->value:
                $this->indexService->deleteFromIndex($element);

                break;
            default:
                throw new InvalidArgumentException(sprintf('Operation %s not valid', $operation));
        }
    }

    /**
     * @throws Exception
     */
    private function handleQueueByOperation(
        ElementInterface $element,
        string $operation,
        bool $processSynchronously
    ): void {
        $this->enqueueService->enqueueRelatedItems(
            element: $element,
            includeElement: !$processSynchronously,
            operation: $operation
        );

        if (($operation === IndexQueueOperation::UPDATE->value) && $element instanceof Asset) {
            $this->enqueueService->enqueueDependentItems(
                element: $element,
                operation: IndexQueueOperation::UPDATE
            );
        }

        if ($operation === IndexQueueOperation::DELETE->value) {
            $this->enqueueService->enqueueDependentItems(
                element: $element,
                operation: IndexQueueOperation::UPDATE
            );
        }
    }

    private function handleEntryByOperation(string $operation, IndexQueue $entry): void
    {
        if ($operation === IndexQueueOperation::DELETE->value) {
            $isClass = false;
            if ($entry->getElementType() === ElementType::DATA_OBJECT->value &&
                $entry->getElementIndexName() !== IndexName::DATA_OBJECT_FOLDER->value
            ) {
                $isClass = true;
            }

            $this->indexService->deleteFromSpecificIndex(
                $this->searchIndexConfigService->getIndexName(
                    $entry->getElementIndexName(),
                    $isClass
                ),
                $entry->getElementId()
            );

            return;
        }

        $element = $this->elementService->getElementByType($entry->getElementId(), $entry->getElementType());
        if ($element) {
            $this->doHandleIndexData($element, $entry->getOperation());
        }
    }

    /**
     * @throws IndexDataException
     */
    private function checkOperationValid(string $operation): void
    {
        if (!in_array($operation, [
            IndexQueueOperation::UPDATE->value,
            IndexQueueOperation::DELETE->value,
        ], true)) {
            throw new IndexDataException(sprintf('Operation %s not valid', $operation));
        }
    }

    private function dispatchEnqueueRelatedIdsMessage(
        ElementInterface $element,
        string $operation,
        bool $addParentElement
    ): void {
        $this->messageBus->dispatch(
            new EnqueueRelatedIdsMessage(
                $element->getId(),
                $this->elementService->getElementType($element),
                $operation,
                $addParentElement
            )
        );
    }
}
