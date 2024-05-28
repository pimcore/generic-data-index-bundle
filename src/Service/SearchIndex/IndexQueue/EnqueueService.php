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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Doctrine\DBAL\Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\EnqueueElementsException;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Tag;

/**
 * @internal
 */
final readonly class EnqueueService implements EnqueueServiceInterface
{
    public function __construct(
        private IndexQueueRepository $indexQueueRepository,
        private TimeServiceInterface $timeService,
        private QueueMessagesDispatcher $queueMessagesDispatcher,
        private AdapterServiceInterface $typeAdapterService,
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
            ['ctype', IndexQueueRepository::AND_OPERATOR => 'tagid']
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
                ElementType::DATA_OBJECT->value,
                IndexQueueOperation::UPDATE->value,
                $this->timeService->getCurrentMillisecondTimestamp(),
                0,
            ],
            'id, className',
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

    public function enqueueDataObjectFolders(): EnqueueServiceInterface
    {
        try {
            $selectQuery = $this->indexQueueRepository->generateSelectQuery(
                'objects',
                [
                    ElementType::DATA_OBJECT->value,
                    IndexName::DATA_OBJECT_FOLDER->value,
                    IndexQueueOperation::UPDATE->value,
                    $this->timeService->getCurrentMillisecondTimestamp(),
                    0,
                ],
            )->where('type = "folder"');
            $this->indexQueueRepository->enqueueBySelectQuery($selectQuery);
        } catch (Exception $e) {
            throw new EnqueueElementsException(
                $e->getMessage()
            );
        }

        return $this;
    }

    /**
     * @throws EnqueueElementsException
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
            throw new EnqueueElementsException(
                $e->getMessage()
            );
        }

        return $this;
    }

    /**
     * @throws EnqueueElementsException
     */
    public function enqueueDocuments(): EnqueueService
    {
        try {
            $selectQuery = $this->indexQueueRepository->generateSelectQuery(
                'documents',
                [
                    ElementType::DOCUMENT->value,
                    IndexName::DOCUMENT->value,
                    IndexQueueOperation::UPDATE->value,
                    $this->timeService->getCurrentMillisecondTimestamp(),
                    0,
                ]
            );
            $this->indexQueueRepository->enqueueBySelectQuery($selectQuery);
        } catch (Exception $e) {
            throw new EnqueueElementsException(
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
