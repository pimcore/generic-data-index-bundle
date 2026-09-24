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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\WorkerPoolBulkDispatcher;

final class BulkDispatcherFactoryTest extends Unit
{
    public function testCreatesTheWorkerPoolWithTheConsoleWorkerCommand(): void
    {
        $factory = new BulkDispatcherFactory(4, '/app', 'prod', false);

        $this->assertInstanceOf(WorkerPoolBulkDispatcher::class, $factory->create());
        $command = $factory->workerCommand();
        $this->assertSame('/app/bin/console', $command[1]);
        $this->assertSame('generic-data-index:snapshot:replay-worker', $command[2]);
        $this->assertContains('--env=prod', $command);
        $this->assertContains('--no-debug', $command, 'workers never run in debug mode unless the importer does');
        $this->assertContains('--no-interaction', $command);
    }

    public function testDebugModeIsPassedOnToTheWorkers(): void
    {
        $factory = new BulkDispatcherFactory(2, '/app', 'dev', true);

        $this->assertNotContains('--no-debug', $factory->workerCommand());
    }
}
