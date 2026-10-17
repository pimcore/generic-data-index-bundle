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
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Command\ReindexItemsCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexRebuild;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexRebuildReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class ReindexItemsCommandTest extends Unit
{
    public function testReturnsSuccessWhenReindexingSucceeds(): void
    {
        $reindexCalls = 0;
        $reindexService = $this->makeEmpty(ReindexServiceInterface::class, [
            'reindexAllIndicesWithReport' => function () use (&$reindexCalls): IndexRebuildReport {
                $reindexCalls++;

                return new IndexRebuildReport();
            },
        ]);

        $commandTester = new CommandTester(new ReindexItemsCommand($reindexService));
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertSame(1, $reindexCalls);
    }

    public function testPrintsANoticeForIndicesQueuedForARebuild(): void
    {
        $reindexService = $this->makeEmpty(ReindexServiceInterface::class, [
            'reindexAllIndicesWithReport' => new IndexRebuildReport([
                new IndexRebuild(
                    'Car',
                    new MappingUpdateResult(['standard_fields.price', 'standard_fields.l_text.en'])
                ),
                new IndexRebuild('assets', new MappingUpdateResult(recreated: true)),
                new IndexRebuild('documents', new MappingUpdateResult(outcomeUnknown: true)),
            ]),
        ]);

        $commandTester = new CommandTester(new ReindexItemsCommand($reindexService));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Car: fields left out of the copy: standard_fields.price, standard_fields.l_text.en',
            $display
        );
        $this->assertStringContainsString('assets: the index had to be created empty', $display);
        $this->assertStringContainsString('documents: the mapping was applied by a custom index handler', $display);
        $this->assertStringContainsString('Run the messenger workers', $display);
    }

    /**
     * A failed reindex must be visible to the calling process (e.g. a deployment
     * pipeline) through a non-zero exit code.
     *
     * @see https://github.com/pimcore/service-operations/issues/853
     */
    public function testReturnsFailureWhenReindexingThrows(): void
    {
        $reindexService = $this->makeEmpty(ReindexServiceInterface::class, [
            'reindexAllIndicesWithReport' => static function (): void {
                throw new Exception('OpenSearch reindex failed (simulated)');
            },
        ]);

        $commandTester = new CommandTester(new ReindexItemsCommand($reindexService));
        $commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('OpenSearch reindex failed (simulated)', $commandTester->getDisplay());
    }
}
