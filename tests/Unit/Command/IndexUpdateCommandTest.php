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
use LogicException;
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
        // The section throws an exception that WRAPS an underlying cause, so the test can assert the
        // whole chain is rendered - not just the outer message. Without a nested cause the cause-chain
        // loop in writeSectionError() could be deleted and this test would stay green.
        $indexUpdateService = $this->makeEmpty(IndexUpdateServiceInterface::class, [
            'updateAll' => function (): void {
                throw new RuntimeException(
                    'mapping update failed (simulated)',
                    0,
                    new LogicException('root cause: invalid mapping definition')
                );
            },
        ]);

        $commandTester = new CommandTester($this->createCommand($indexUpdateService));
        $commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        // the outer exception type and message must be visible
        $this->assertStringContainsString('RuntimeException', $display);
        $this->assertStringContainsString('mapping update failed (simulated)', $display);
        // ...and so must the preserved cause chain (type + message of the previous exception)
        $this->assertStringContainsString('caused by', $display);
        $this->assertStringContainsString('LogicException', $display);
        $this->assertStringContainsString('root cause: invalid mapping definition', $display);
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
     * An exception message containing console-style markup must not make the error reporting throw
     * from OutputFormatter - that would abort the remaining sections and the queue dispatch, the
     * opposite of what this command promises. The dynamic values are escaped, so the run completes.
     */
    public function testExceptionMessageWithConsoleMarkupDoesNotAbortTheRun(): void
    {
        $dispatched = false;
        $indexUpdateService = $this->makeEmpty(IndexUpdateServiceInterface::class, [
            'updateAll' => function (): void {
                // `<fg=bogus>` is invalid console markup: OutputFormatter throws on it unless escaped.
                throw new RuntimeException('invalid mapping <fg=bogus>value</>');
            },
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'dispatchQueueMessages' => function () use (&$dispatched): void {
                $dispatched = true;
            },
        ]);

        $commandTester = new CommandTester($this->createCommand($indexUpdateService, $enqueueService));
        $commandTester->execute([]); // must not throw

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertTrue($dispatched, 'queue dispatch must still run even when the error message contains markup');
        // the markup is rendered literally rather than interpreted as a (broken) style tag
        $this->assertStringContainsString('invalid mapping <fg=bogus>value</>', $commandTester->getDisplay());
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
