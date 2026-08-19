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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\MessageHandler;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Doctrine\Persistence\ConnectionRegistry;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\IndexUpdateQueueMessage;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\IndexUpdateQueueHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Helper\LongRunningHelper;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * @internal
 */
final class IndexUpdateQueueHandlerTest extends Unit
{
    private const RUNTIME_CACHE_KEY = 'gdi_index_update_queue_handler_test_item';

    public function testRuntimeCacheIsClearedAfterProcessingABatch(): void
    {
        $handler = new IndexUpdateQueueHandler(
            $this->makeEmpty(IndexQueueServiceInterface::class),
            $this->createRepositoryInstance(),
            $this->createLongRunningHelper(),
        );

        RuntimeCache::set(self::RUNTIME_CACHE_KEY, 'accumulated state');

        $handler(new IndexUpdateQueueMessage([]));

        $this->assertFalse(
            RuntimeCache::isRegistered(self::RUNTIME_CACHE_KEY),
            'Runtime cache must be cleaned up after a batch, ' .
            'otherwise long-running queue workers accumulate memory'
        );
    }

    public function testRuntimeCacheIsClearedEvenWhenProcessingFails(): void
    {
        $handler = new IndexUpdateQueueHandler(
            $this->makeEmpty(IndexQueueServiceInterface::class, [
                'handleIndexQueueEntries' => Expected::once(
                    static fn () => throw new Exception('processing failed')
                ),
            ]),
            $this->createRepositoryInstance(),
            $this->createLongRunningHelper(),
        );

        RuntimeCache::set(self::RUNTIME_CACHE_KEY, 'accumulated state');

        $caught = null;

        try {
            $handler(new IndexUpdateQueueMessage([]));
        } catch (Exception $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'The processing exception must propagate for messenger retry handling');
        $this->assertSame('processing failed', $caught->getMessage());
        $this->assertFalse(
            RuntimeCache::isRegistered(self::RUNTIME_CACHE_KEY),
            'Runtime cache must be cleaned up even when batch processing fails'
        );
    }

    private function createLongRunningHelper(): LongRunningHelper
    {
        $longRunningHelper = new LongRunningHelper(
            $this->makeEmpty(ConnectionRegistry::class, ['getConnections' => []])
        );
        $longRunningHelper->setLogger(new NullLogger());

        return $longRunningHelper;
    }

    private function createRepositoryInstance(): IndexQueueRepository
    {
        // IndexQueueRepository is final and cannot be doubled; with an empty
        // entries list it is never used, so an uninitialized instance is safe.
        return (new ReflectionClass(IndexQueueRepository::class))
            ->newInstanceWithoutConstructor();
    }
}
