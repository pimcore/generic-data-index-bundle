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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Command\Update\IndexUpdateCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class IndexUpdateCommandTest extends Unit
{
    /**
     * A failing section must surface as a non-zero exit code so deployment pipelines see it,
     * instead of the command reporting success on a broken index (PEES-1206 / #853).
     */
    public function testReturnsFailureWhenAnUpdateSectionThrows(): void
    {
        $indexUpdateService = $this->makeEmpty(IndexUpdateServiceInterface::class, [
            'updateAll' => function (): void {
                throw new RuntimeException('mapping update failed (simulated)');
            },
        ]);

        $commandTester = new CommandTester($this->createCommand($indexUpdateService));
        $commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        // the exception type and message must both be visible (cause preservation)
        $this->assertStringContainsString('RuntimeException', $display);
        $this->assertStringContainsString('mapping update failed (simulated)', $display);
    }

    /**
     * All sections are still attempted, and the queue is still dispatched, even when one fails.
     */
    public function testDispatchesQueueEvenWhenASectionFails(): void
    {
        $dispatched = false;
        $indexUpdateService = $this->makeEmpty(IndexUpdateServiceInterface::class, [
            'updateAll' => function (): void {
                throw new RuntimeException('boom');
            },
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'dispatchQueueMessages' => function () use (&$dispatched): void {
                $dispatched = true;
            },
        ]);

        $commandTester = new CommandTester($this->createCommand($indexUpdateService, $enqueueService));
        $commandTester->execute([]);

        $this->assertTrue($dispatched, 'queue dispatch must still run so already-updated work is processed');
        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    /**
     * The happy path still returns success.
     */
    public function testReturnsSuccessWhenAllSectionsSucceed(): void
    {
        $commandTester = new CommandTester($this->createCommand());
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    private function createCommand(
        ?IndexUpdateServiceInterface $indexUpdateService = null,
        ?EnqueueServiceInterface $enqueueService = null,
    ): IndexUpdateCommand {
        $command = new IndexUpdateCommand();
        $command->setIndexUpdateService($indexUpdateService ?? $this->makeEmpty(IndexUpdateServiceInterface::class));
        $command->setEnqueueService($enqueueService ?? $this->makeEmpty(EnqueueServiceInterface::class));
        $command->setGlobalIndexAliasService($this->makeEmpty(GlobalIndexAliasServiceInterface::class));

        return $command;
    }
}
