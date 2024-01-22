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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Doctrine\DBAL\Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\AbstractIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeService;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Tag;

class EnqueueService
{
    public function __construct(
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly TimeService $timeService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
    ) {

    }

    /**
     * @throws Exception
     */
    public function enqueueByTag(Tag $tag): EnqueueService
    {
        $tagCondition = "WHERE id IN (SELECT cid FROM tags_assignment WHERE ctype='%s' AND tagid = %s)";

        //assets
        $this->indexQueueRepository->enqueueBySelectQuery(
            sprintf("SELECT id, '%s', '%s', '%s', '%s', 0 FROM assets " . $tagCondition,
                ElementType::ASSET->value,
                IndexName::ASSET->value,
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                $tag->getId(),
                'asset'
            )
        );

        //data objects
        $this->indexQueueRepository->enqueueBySelectQuery(
            sprintf("SELECT id, className, '%s', '%s', '%s', 0 FROM objects " . $tagCondition,
                ElementType::DATA_OBJECT->value,
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                $tag->getId(),
                'object'
            )
        );

        return $this;
    }

    /**
     * @throws Exception
     */
    public function enqueueByClassDefinition(ClassDefinition $classDefinition): EnqueueService
    {
        $dataObjectTableName = 'object_' . $classDefinition->getId();

        $this->indexQueueRepository->enqueueBySelectQuery(
            sprintf("SELECT oo_id, '%s', '%s', '%s', '%s', 0 FROM %s",
                ElementType::DATA_OBJECT->value,
                $classDefinition->getName(),
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                $dataObjectTableName
            )
        );

        return $this;
    }

    /**
     * @throws Exception
     */
    public function enqueueAssets(): EnqueueService
    {
        $this->indexQueueRepository->enqueueBySelectQuery(
            sprintf("SELECT id, '%s', '%s', '%s', '%s', 0 FROM %s",
                ElementType::ASSET->value,
                IndexName::ASSET->value,
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                'assets'
            )
        );

        return $this;
    }

    /**
     * @throws Exception
     */
    public function enqueueRelatedItemsOnUpdate(
        AbstractIndexService $indexService,
        ElementInterface $element,
        bool $includeElement
    ) {
        $subQuery = $indexService->getRelatedItemsOnUpdateQuery(
            element: $element,
            operation: IndexQueueOperation::UPDATE->value,
            operationTime: $this->timeService->getCurrentMillisecondTimestamp(),
            includeElement: $includeElement,
        );

        if ($subQuery) {
            $this->indexQueueRepository->enqueueBySelectQuery($subQuery->getSQL(), $subQuery->getParameters());
        }
    }

    public function dispatchQueueMessages(bool $synchronously = false): void
    {
        $this->queueMessagesDispatcher->dispatchQueueMessages($synchronously);
    }
}
