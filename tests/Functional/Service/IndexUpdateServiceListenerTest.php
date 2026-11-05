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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Service;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * The index update listener is what keeps the lock of IndexSyncService alive, so the real IndexUpdateService
 * has to call it right before and right after every single index it updates.
 */
class IndexUpdateServiceListenerTest extends Unit
{
    protected IndexTester $tester;

    private IndexUpdateService $indexUpdateService;

    protected function _before(): void
    {
        $this->tester->clearQueue();

        /** @var IndexUpdateService $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $this->indexUpdateService = $indexUpdateService->setReCreateIndex(false)->setIndexUpdateListener(null);
    }

    protected function _after(): void
    {
        $this->indexUpdateService->setIndexUpdateListener(null);
        $this->tester->clearQueue();
    }

    public function testListenerRunsBeforeAndAfterEveryIndexUpdate(): void
    {
        $classDefinition = ClassDefinition::getByName('simple');

        $updates = [
            'data object folders' => fn () => $this->indexUpdateService->updateDataObjectFolders(),
            'class definition' => fn () => $this->indexUpdateService->updateClassDefinition($classDefinition),
            'assets' => fn () => $this->indexUpdateService->updateAssets(),
            'documents' => fn () => $this->indexUpdateService->updateDocuments(),
        ];

        foreach ($updates as $name => $update) {
            $calls = 0;
            $this->indexUpdateService->setIndexUpdateListener(function () use (&$calls): void {
                $calls++;
            });

            $update();

            $this->assertSame(2, $calls, sprintf('The listener must run before and after the update of the %s', $name));
        }
    }

    public function testUpdateAllNotifiesTheListenerForEachIndex(): void
    {
        $calls = 0;
        $this->indexUpdateService->setIndexUpdateListener(function () use (&$calls): void {
            $calls++;
        });

        $this->indexUpdateService->updateAll();

        $classes = count((new ClassDefinition\Listing())->load());
        // data object folders, every class definition, assets and documents, each notified twice
        $this->assertSame(2 * (1 + $classes + 2), $calls);
    }

    public function testFailingListenerBeforeTheUpdateStopsItBeforeAnythingIsQueued(): void
    {
        $this->indexUpdateService->setIndexUpdateListener(static function (): void {
            throw new Exception('lock lost');
        });

        try {
            $this->indexUpdateService->updateAssets();
            $this->fail('The update must stop when the listener fails');
        } catch (Exception $exception) {
            $this->assertSame('lock lost', $exception->getMessage());
        }

        $this->assertSame(0, $this->countQueueEntries(), 'Nothing may be queued once the listener failed');
    }

    public function testFailingListenerStopsFurtherIndicesOfUpdateAll(): void
    {
        $calls = 0;
        $this->indexUpdateService->setIndexUpdateListener(function () use (&$calls): void {
            // the third call is the one before the first class definition: the data object folders are done
            if (++$calls === 3) {
                throw new Exception('lock lost');
            }
        });

        try {
            $this->indexUpdateService->updateAll();
            $this->fail('The update must stop when the listener fails');
        } catch (Exception $exception) {
            $this->assertSame('lock lost', $exception->getMessage());
        }

        $this->assertSame(3, $calls, 'No further index may be touched after the listener failed');
    }

    private function countQueueEntries(): int
    {
        return (int) Db::get()->fetchOne('select count(*) from generic_data_index_queue');
    }
}
