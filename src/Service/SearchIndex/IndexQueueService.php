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
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexDataException;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;

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
    ) {
    }

    public function updateIndexQueue(
        ElementInterface $element,
        string $operation,
        bool $processSynchronously = false
    ): IndexQueueService {
        try {
            $this->checkOperationValid($operation);

            if ($processSynchronously) {
                $this->doHandleIndexData($element, $operation);
            }

            $this->enqueueService->enqueueRelatedItemsOnUpdate(
                element: $element,
                includeElement: !$processSynchronously
            );

            if ($element instanceof Asset) {
                foreach ($this->indexService->updateAssetDependencies($element) as $asset) {
                    $this->updateIndexQueue($asset, IndexQueueOperation::UPDATE->value);
                }
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
     */
    public function handleIndexQueueEntries(array $entries): void
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
                $element = $this->elementService->getElementByType($entry->getElementId(), $entry->getElementType());
                if ($element) {
                    $this->doHandleIndexData($element, $entry->getOperation());
                }
            }

            $this->bulkOperationService->commit();
            $this->indexQueueRepository->deleteQueueEntries($entries);

        } catch (Exception $e) {
            $this->logger->warning('handleIndexQueueEntry failed! Error: ' . $e->getMessage());
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
        }
    }

    /**
     * @throws IndexDataException
     */
    private function checkOperationValid(string $operation): void
    {
        if(!in_array($operation, [
            IndexQueueOperation::UPDATE->value,
            IndexQueueOperation::DELETE->value,
        ], true)) {
            throw new IndexDataException(sprintf('Operation %s not valid', $operation));
        }
    }
}
