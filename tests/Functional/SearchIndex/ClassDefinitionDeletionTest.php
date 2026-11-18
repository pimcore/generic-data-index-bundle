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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * Regression tests for pimcore/platform-version#647: deleting a class deletes its objects, which queues a delete
 * entry per object, and drops the class index right away. The entries must not be left behind to fail on every
 * dispatch, and a worker that already holds them must not fail its batch over the missing index.
 *
 * @internal
 */
final class ClassDefinitionDeletionTest extends Unit
{
    private const CLASS_NAME = 'deletionTest647';

    protected IndexTester $tester;

    private IndexQueueRepository $queueRepository;

    private IndexQueueServiceInterface $queueService;

    protected function _before(): void
    {
        $this->queueRepository = $this->tester->grabService(IndexQueueRepository::class);
        $this->queueService = $this->tester->grabService(IndexQueueServiceInterface::class);
        $this->tester->disableSynchronousProcessing();
        // the entries must land in the queue table in this process, not through a messenger message
        $this->tester->enableSynchronousProcessingRelatedIds();
        $this->tester->clearQueue();
    }

    protected function _after(): void
    {
        ClassDefinition::getByName(self::CLASS_NAME)?->delete();
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testDeletingAClassLeavesNoQueueEntriesBehind(): void
    {
        $classDefinition = $this->createClassWithIndex();
        $indexName = $this->tester->grabService(DataObjectTypeAdapter::class)->getAliasIndexName($classDefinition);
        $objectIds = [];
        for ($i = 0; $i < 3; $i++) {
            $objectIds[] = $this->createObject($classDefinition)->getId();
        }
        $this->processQueue();
        foreach ($objectIds as $objectId) {
            $this->tester->checkIndexEntry($objectId, $indexName);
        }

        $classDefinition->delete();

        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        $this->assertFalse($searchIndexService->existsIndex($indexName), 'The class index is deleted with the class');
        $this->assertSame(
            0,
            $this->countEntriesOfClass(),
            'The queue entries of the deleted objects are removed together with their index'
        );

        $this->processQueue();

        $this->assertSame(0, $this->queueRepository->countIndexQueueEntries());
    }

    /**
     * A worker that claimed the entries before the class was deleted still processes them after its index is
     * gone: the deletions are done, and the other elements of the same batch are not blocked by them.
     */
    public function testQueuedDeletesForAMissingIndexAreDoneAndDoNotBlockTheBatch(): void
    {
        $classDefinition = $this->createClassWithIndex();
        $object = $this->createObject($classDefinition);
        $this->processQueue();
        $object->delete();
        $asset = TestHelper::createImageAsset('647-');
        $batch = $this->claimQueue();
        $this->assertCount(2, $batch, 'The batch spans the deleted object and the asset');

        $classDefinition->delete();

        $this->queueService->handleIndexQueueEntries($batch);
        $this->tester->flushIndex();

        $this->assertSame(0, $this->queueRepository->countIndexQueueEntries(), 'The whole batch is processed');
        /** @var SearchIndexConfigServiceInterface $configService */
        $configService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $this->tester->checkIndexEntry($asset->getId(), $configService->getIndexName(IndexName::ASSET->value));
    }

    private function createClassWithIndex(): ClassDefinition
    {
        $classDefinition = $this->tester->setupPimcoreClass_Simple(self::CLASS_NAME, self::CLASS_NAME . '.json');
        $this->assertNotNull($classDefinition);
        /** @var IndexUpdateServiceInterface $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $indexUpdateService->setReCreateIndex(true)->updateClassDefinition($classDefinition);
        $this->tester->clearQueue();

        return $classDefinition;
    }

    private function createObject(ClassDefinition $classDefinition): Concrete
    {
        $object = TestHelper::createEmptyObject(
            '647-',
            true,
            true,
            'Pimcore\\Model\\DataObject\\' . ucfirst($classDefinition->getName())
        );
        $this->assertSame($classDefinition->getName(), $object->getClassName());

        return $object;
    }

    private function countEntriesOfClass(): int
    {
        return (int)Db::get()->fetchOne(
            'SELECT COUNT(*) FROM ' . IndexQueue::TABLE . ' WHERE elementIndexName = ?',
            [self::CLASS_NAME]
        );
    }

    /**
     * @return IndexQueue[]
     */
    private function claimQueue(): array
    {
        return array_map(
            fn (array $entry): IndexQueue => $this->queueRepository->denormalizeDatabaseEntry($entry),
            $this->queueRepository->getUnhandledIndexQueueEntries(true)
        );
    }

    /**
     * Processes the queue in this process; a failing batch surfaces as the exception the worker would see.
     */
    private function processQueue(): void
    {
        for ($i = 0; $i < 5 && $this->queueRepository->countIndexQueueEntries() > 0; $i++) {
            $this->queueService->handleIndexQueueEntries($this->claimQueue());
        }
        $this->tester->flushIndex();
    }
}
