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

use Codeception\Stub;
use Codeception\Stub\Expected;
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
    public function testReturnsSuccessOnCleanRun(): void
    {
        $reindexService = Stub::makeEmpty(ReindexServiceInterface::class, [
            'reindexAllIndices' => Expected::once(),
        ]);

        $tester = new CommandTester(new ReindexItemsCommand($reindexService));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testReturnsFailureWhenReindexAllIndicesThrows(): void
    {
        $reindexService = Stub::makeEmpty(ReindexServiceInterface::class, [
            'reindexAllIndices' => function (): void {
                throw new Exception('Reindex blew up');
            },
        ]);

        $tester = new CommandTester(new ReindexItemsCommand($reindexService));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Reindex blew up', $tester->getDisplay());
    }
}
