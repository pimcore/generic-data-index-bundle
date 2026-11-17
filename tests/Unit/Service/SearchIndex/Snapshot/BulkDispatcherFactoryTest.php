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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkDispatcherFactory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkSenderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\InProcessBulkDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\WorkerPoolBulkDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot\Support\DescriptorPressure;

final class BulkDispatcherFactoryTest extends Unit
{
    private const PROJECT_DIR = '/app';

    public function testCreatesTheWorkerPoolWithTheConsoleWorkerCommand(): void
    {
        $factory = new BulkDispatcherFactory(4, self::PROJECT_DIR, 'prod', false);

        $this->assertInstanceOf(WorkerPoolBulkDispatcher::class, $factory->create());
        $command = $factory->workerCommand();
        $this->assertSame(self::PROJECT_DIR . '/bin/console', $command[1]);
        $this->assertSame('generic-data-index:snapshot:replay-worker', $command[2]);
        $this->assertContains('--env=prod', $command);
        $this->assertContains('--no-debug', $command, 'workers never run in debug mode unless the importer does');
        $this->assertContains('--no-interaction', $command);
    }

    public function testDebugModeIsPassedOnToTheWorkers(): void
    {
        $factory = new BulkDispatcherFactory(2, self::PROJECT_DIR, 'dev', true);

        $this->assertNotContains('--no-debug', $factory->workerCommand());
    }

    public function testTheInProcessDispatcherReplacesTheWorkerPoolAboveTheSelectLimit(): void
    {
        $factory = new BulkDispatcherFactory(
            4,
            self::PROJECT_DIR,
            'prod',
            false,
            null,
            $this->createMock(BulkSenderInterface::class)
        );
        $this->assertInstanceOf(WorkerPoolBulkDispatcher::class, $factory->create());

        $pressure = new DescriptorPressure();
        if (!$pressure->raiseAboveSelectLimit()) {
            $pressure->release();
            $this->markTestSkipped('this process may not open more than FD_SETSIZE descriptors');
        }

        try {
            $this->assertInstanceOf(
                InProcessBulkDispatcher::class,
                $factory->create(),
                'a pipe to a worker cannot be driven from a process with that many open descriptors'
            );
        } finally {
            $pressure->release();
        }
    }

    public function testTheWorkerPoolIsKeptWhenNoBulkSenderWasPassed(): void
    {
        // A caller built against the constructor of 2026.3 passes no sender, so there is nothing to
        // fall back to; the worker pool stays, as it did before the fallback existed.
        $factory = new BulkDispatcherFactory(4, self::PROJECT_DIR, 'prod', false);

        $pressure = new DescriptorPressure();
        if (!$pressure->raiseAboveSelectLimit()) {
            $pressure->release();
            $this->markTestSkipped('this process may not open more than FD_SETSIZE descriptors');
        }

        try {
            $this->assertInstanceOf(WorkerPoolBulkDispatcher::class, $factory->create());
        } finally {
            $pressure->release();
        }
    }
}
