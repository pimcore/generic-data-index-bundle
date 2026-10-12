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
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Command\DeploymentReindexCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexPlan;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexState;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\DeploymentReindexServiceInterface;
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
            new DeploymentReindexCommand(
                $enqueueService,
                $classDefinitionReindexService,
                $this->makeEmpty(DeploymentReindexServiceInterface::class)
            )
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
            new DeploymentReindexCommand(
                $enqueueService,
                $classDefinitionReindexService,
                $this->makeEmpty(DeploymentReindexServiceInterface::class)
            )
        );
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('No updates needed', $commandTester->getDisplay());
    }

    public function testWithoutIndexOptionsTheExtendedChecksAreNotUsed(): void
    {
        $commandTester = new CommandTester(new DeploymentReindexCommand(
            $this->makeEmpty(EnqueueServiceInterface::class),
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, ['reindexClassDefinition' => false]),
            $this->makeEmpty(DeploymentReindexServiceInterface::class, [
                'getTargets' => Expected::never(),
                'execute' => Expected::never(),
            ])
        ));

        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    /**
     * A failing index must not keep the others from being updated, and the run must still fail.
     */
    public function testFailedIndexDoesNotStopTheOthers(): void
    {
        $assets = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);
        $documents = IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS);
        $deploymentReindexService = $this->makeEmpty(DeploymentReindexServiceInterface::class, [
            'getTargets' => Expected::once(static function (array $scopes) use ($assets, $documents): array {
                self::assertSame([DeploymentReindexScope::ASSETS, DeploymentReindexScope::DOCUMENTS], $scopes);

                return [$assets, $documents];
            }),
            'plan' => static fn (IndexStateTarget $target): IndexPlan => new IndexPlan(
                $target,
                new IndexState('mapping', 'settings', '1.0'),
                false,
                true,
                false,
                false,
                false,
                ['mapping changed']
            ),
            'execute' => Expected::exactly(2, static function (IndexPlan $plan): bool {
                if ($plan->getTarget()->getKey() === 'asset') {
                    throw new Exception('asset reindex failed (simulated)');
                }

                return true;
            }),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'dispatchQueueMessages' => Expected::once(),
        ]);

        $commandTester = new CommandTester(new DeploymentReindexCommand(
            $enqueueService,
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class),
            $deploymentReindexService
        ));
        $commandTester->execute(['--assets' => true, '--documents' => true]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Updating assets failed: asset reindex failed (simulated)',
            $commandTester->getDisplay()
        );
    }

    public function testDryRunExecutesNothing(): void
    {
        $deploymentReindexService = $this->makeEmpty(DeploymentReindexServiceInterface::class, [
            'getTargets' => [IndexStateTarget::forScope(DeploymentReindexScope::ASSETS)],
            'plan' => static fn (IndexStateTarget $target): IndexPlan => new IndexPlan(
                $target,
                new IndexState('mapping', 'settings', '1.0'),
                false,
                true,
                false,
                false,
                false,
                ['mapping changed']
            ),
            'execute' => Expected::never(),
        ]);

        $commandTester = new CommandTester(new DeploymentReindexCommand(
            $this->makeEmpty(EnqueueServiceInterface::class, ['dispatchQueueMessages' => Expected::never()]),
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class),
            $deploymentReindexService
        ));
        $commandTester->execute(['--all' => true, '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('native reindex, rebuild from database', $commandTester->getDisplay());
    }
}
