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
        $reindexService = null;
        $reindexService = $this->makeEmpty(ReindexServiceInterface::class, [
            'reindexAllIndices' => function () use (&$reindexCalls, &$reindexService): ReindexServiceInterface {
                $reindexCalls++;

                return $reindexService;
            },
        ]);

        $commandTester = new CommandTester(new ReindexItemsCommand($reindexService));
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertSame(1, $reindexCalls);
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
            'reindexAllIndices' => static function (): void {
                throw new Exception('OpenSearch reindex failed (simulated)');
            },
        ]);

        $commandTester = new CommandTester(new ReindexItemsCommand($reindexService));
        $commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('OpenSearch reindex failed (simulated)', $commandTester->getDisplay());
    }
}
