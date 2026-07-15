<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Functional\Command;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Command\DeploymentReindexCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DeploymentReindexCommandTest extends Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before(): void
    {
        // The command iterates all class definitions; the functional suite provisions
        // several, so the loop body is guaranteed to run.
        $this->assertNotEmpty(
            (new ClassDefinition\Listing())->load(),
            'Precondition failed: no class definitions available in the test environment'
        );
    }

    /**
     * When reindexing a class definition fails, the deployment pipeline must see a
     * non-zero exit code instead of silently continuing with a stale index.
     *
     * @see https://github.com/pimcore/service-operations/issues/853
     */
    public function testReturnsFailureWhenClassDefinitionReindexThrows(): void
    {
        $classDefinitionReindexService = $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
            'reindexClassDefinition' => static function (): bool {
                throw new ClassDefinitionIndexUpdateFailedException('OpenSearch reindex failed (simulated 504)');
            },
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'dispatchQueueMessages' => Expected::never(),
        ]);

        $commandTester = new CommandTester(
            new DeploymentReindexCommand($enqueueService, $classDefinitionReindexService)
        );
        $commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('OpenSearch reindex failed (simulated 504)', $commandTester->getDisplay());
    }

    public function testReturnsSuccessWhenNoClassDefinitionChanged(): void
    {
        $classDefinitionReindexService = $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
            'reindexClassDefinition' => false,
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'dispatchQueueMessages' => Expected::never(),
        ]);

        $commandTester = new CommandTester(
            new DeploymentReindexCommand($enqueueService, $classDefinitionReindexService)
        );
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('No updates needed', $commandTester->getDisplay());
    }
}
