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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Command\StatusCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStatsIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class StatusCommandTest extends Unit
{
    /**
     * The status overview lists each index with its document count and reports the queue depth.
     */
    public function testRendersIndicesAndQueueDepth(): void
    {
        $stats = new IndexStats(7, [
            new IndexStatsIndex('dataobject_car-even', 1234, 512.0),
            new IndexStatsIndex('asset-even', 42, 8.5),
        ]);

        $commandTester = new CommandTester($this->createCommand($stats));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('dataobject_car-even', $display);
        $this->assertStringContainsString('1234', $display);
        $this->assertStringContainsString('asset-even', $display);
        // queue depth from the stats
        $this->assertStringContainsString('7', $display);
        // empty (makeEmpty) queue repository reports nothing pending dispatch
        $this->assertStringContainsString('no', $display);
    }

    /**
     * An index that exists in BOTH the -even and -odd version at once is the fingerprint of an
     * interrupted reindex and must be surfaced as a warning.
     */
    public function testWarnsOnInterruptedReindexLeftover(): void
    {
        $stats = new IndexStats(0, [
            new IndexStatsIndex('dataobject_car-even', 10, 1.0),
            new IndexStatsIndex('dataobject_car-odd', 10, 1.0),
            new IndexStatsIndex('asset-even', 5, 1.0),
        ]);

        $commandTester = new CommandTester($this->createCommand($stats));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('interrupted reindex', $display);
        $this->assertStringContainsString('dataobject_car', $display);
        // the healthy single-version index must NOT be flagged as a leftover
        $this->assertStringNotContainsString("\n  asset\n", $display);
    }

    /**
     * With no indices at all the command hints at building the index rather than printing an empty
     * table, and still exits cleanly (read-only diagnostic).
     */
    public function testWarnsWhenNoIndicesExist(): void
    {
        $commandTester = new CommandTester($this->createCommand(new IndexStats(0, [])));
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('No indices found', $commandTester->getDisplay());
    }

    private function createCommand(IndexStats $stats): StatusCommand
    {
        $statsService = $this->makeEmpty(IndexStatsServiceInterface::class, [
            'getStats' => $stats,
        ]);

        return new StatusCommand($statsService, $this->getEmptyQueueRepository());
    }

    /**
     * A real (final) repository wired with empty collaborators: every query resolves to nothing,
     * so dispatchableItemExists() deterministically returns false without a database.
     */
    private function getEmptyQueueRepository(): IndexQueueRepository
    {
        return new IndexQueueRepository(
            $this->makeEmpty(EntityManagerInterface::class),
            $this->makeEmpty(TimeServiceInterface::class),
            $this->makeEmpty(Connection::class),
            $this->makeEmpty(DenormalizerInterface::class)
        );
    }
}
