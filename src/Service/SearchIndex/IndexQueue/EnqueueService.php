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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\EnqueueAssetsException;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Tag;

/**
 * @internal
 */
final class EnqueueService implements EnqueueServiceInterface
{
    public function __construct(
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly TimeServiceInterface $timeService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
        private readonly AdapterServiceInterface $typeAdapterService,
    ) {

    }

    /**
     * @throws Exception
     */
    public function enqueueByTag(Tag $tag): EnqueueService
    {
        $tagCondition = $this->indexQueueRepository->generateSelectQuery(
            'tags_assignment',
            [],
            'cid',
            [],
            ['ctype', 'tagid' => IndexQueueRepository::AND_OPERATOR]
        );

        //assets
        $assetQuery = $this->indexQueueRepository->generateSelectQuery(
            'assets',
            [
                ElementType::ASSET->value,
                IndexName::ASSET->value,
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                0,
            ],
            'id',
            ['ctype' => ElementType::ASSET->value, 'tagid' => $tag->getId()],
        );
        $assetQuery->where($assetQuery->expr()->in('id', $tagCondition->getSQL()));
        $this->indexQueueRepository->enqueueBySelectQuery($assetQuery);

        //data objects
        $dataObjectQuery = $this->indexQueueRepository->generateSelectQuery(
            'objects',
            [
                'className',
                ElementType::DATA_OBJECT->value,
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                0,
            ],
            'id',
            ['ctype' => ElementType::DATA_OBJECT->value, 'tagid' => $tag->getId()],
        );
        $dataObjectQuery->where($dataObjectQuery->expr()->in('id', $tagCondition->getSQL()));
        $this->indexQueueRepository->enqueueBySelectQuery($dataObjectQuery);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function enqueueByClassDefinition(ClassDefinition $classDefinition): EnqueueService
    {
        $dataObjectTableName = 'object_' . $classDefinition->getId();
        $selectQuery = $this->indexQueueRepository->generateSelectQuery(
            $dataObjectTableName,
            [
                ElementType::DATA_OBJECT->value,
                $classDefinition->getName(),
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                0,
            ],
            'oo_id'
        );
        $this->indexQueueRepository->enqueueBySelectQuery(
            $selectQuery
        );

        return $this;
    }

    /**
     * @throws EnqueueAssetsException
     */
    public function enqueueAssets(): EnqueueService
    {
        try {
            $selectQuery = $this->indexQueueRepository->generateSelectQuery(
                'assets',
                [
                    ElementType::ASSET->value,
                    IndexName::ASSET->value,
                    IndexQueueOperation::UPDATE->value,
                    $this->timeService->getCurrentMillisecondTimestamp(),
                    0,
                ]
            );
            $this->indexQueueRepository->enqueueBySelectQuery($selectQuery);
        } catch (Exception $e) {
            throw new EnqueueAssetsException(
                $e->getMessage()
            );
        }

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function enqueueRelatedItemsOnUpdate(
        ElementInterface $element,
        bool $includeElement
    ): void {
        $subQuery = $this->typeAdapterService
            ->getTypeAdapter($element)
            ->getRelatedItemsOnUpdateQuery(
                element: $element,
                operation: IndexQueueOperation::UPDATE->value,
                operationTime: $this->timeService->getCurrentMillisecondTimestamp(),
                includeElement: $includeElement,
            );

        if ($subQuery) {
            $this->indexQueueRepository->enqueueBySelectQuery($subQuery);
        }
    }

    public function dispatchQueueMessages(bool $synchronously = false): void
    {
        $this->queueMessagesDispatcher->dispatchQueueMessages($synchronously);
    }
}
