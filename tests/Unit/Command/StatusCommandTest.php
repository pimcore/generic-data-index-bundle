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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatusReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStatsIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexCountRepositoryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStatusServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final class StatusCommandTest extends Unit
{
    /**
     * The status overview lists each index with its document count, reports the queue depth and the state of
     * every index.
     */
    public function testRendersIndicesQueueDepthAndStatus(): void
    {
        $stats = new IndexStats(7, [
            new IndexStatsIndex('dataobject_car-even', 1234, 512.0),
            new IndexStatsIndex('asset-even', 42, 8.5),
        ]);
        $report = new IndexStatusReport([
            $this->makeStatus('pimcore_asset', IndexStatusState::COMPLETE),
            $this->makeStatus('pimcore_data-object_car', IndexStatusState::REBUILDING, pending: 25),
        ], 7, 3);

        $commandTester = new CommandTester($this->createCommand($stats, $report));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('dataobject_car-even', $display);
        $this->assertStringContainsString('1234', $display);
        $this->assertStringContainsString('asset-even', $display);
        $this->assertStringContainsString('7', $display);
        $this->assertStringContainsString('failed messages', $display);
        $this->assertStringContainsString('rebuilding', $display);
        $this->assertStringContainsString('75.0 %', $display);
        $this->assertStringNotContainsString('All indices are complete', $display);
    }

    /**
     * An index that exists in BOTH the -even and -odd version at once is the fingerprint of an interrupted
     * reindex, unless a copy of that index is running right now.
     */
    public function testWarnsOnInterruptedReindexLeftoverUnlessACopyRuns(): void
    {
        $stats = new IndexStats(0, [
            new IndexStatsIndex('pimcore_data-object_car-even', 10, 1.0),
            new IndexStatsIndex('pimcore_data-object_car-odd', 10, 1.0),
            new IndexStatsIndex('pimcore_asset-even', 5, 1.0),
            new IndexStatsIndex('pimcore_asset-odd', 5, 1.0),
        ]);
        $report = new IndexStatusReport([
            $this->makeStatus('pimcore_asset', IndexStatusState::COPYING, copyRunning: true),
            $this->makeStatus('pimcore_data-object_car', IndexStatusState::COMPLETE),
        ], 0, null);

        $commandTester = new CommandTester($this->createCommand($stats, $report));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('interrupted reindex', $display);
        $this->assertStringContainsString("\n  pimcore_data-object_car", $display);
        $this->assertStringNotContainsString("\n  pimcore_asset\n", $display, 'A running copy is not a leftover');
    }

    public function testWarnsWhenNoIndicesExist(): void
    {
        $commandTester = new CommandTester(
            $this->createCommand(new IndexStats(0, []), new IndexStatusReport([], 0, null))
        );
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('No indices found', $commandTester->getDisplay());
    }

    public function testCheckFailsWhileAnIndexIsNotComplete(): void
    {
        $report = new IndexStatusReport([$this->makeStatus('pimcore_asset', IndexStatusState::INCOMPLETE)], 0, null);
        $commandTester = new CommandTester($this->createCommand(new IndexStats(0, []), $report));

        $commandTester->execute(['--check' => true]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Not complete: assets (incomplete)', $commandTester->getDisplay());
    }

    public function testJsonOutputCarriesTheReport(): void
    {
        $report = new IndexStatusReport([$this->makeStatus('pimcore_asset', IndexStatusState::COMPLETE)], 2, 0);
        $commandTester = new CommandTester($this->createCommand(new IndexStats(2, []), $report));

        $commandTester->execute(['--json' => true]);

        $json = json_decode($commandTester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($json['complete']);
        $this->assertSame('pimcore_asset', $json['indices'][0]['index']);
        $this->assertSame('complete', $json['indices'][0]['state']);
    }

    public function testASingleIndexIsReadByName(): void
    {
        $commandTester = new CommandTester($this->createCommand(
            new IndexStats(0, []),
            new IndexStatusReport([], 0, null),
            byName: [
                'Car' => $this->makeStatus('pimcore_data-object_car', IndexStatusState::DEGRADED, withoutChecksum: 5),
            ]
        ));

        $commandTester->execute(['--index' => 'Car']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('degraded', $commandTester->getDisplay());
        $this->assertStringContainsString('50.0 %', $commandTester->getDisplay());
    }

    public function testAnUnknownIndexNameFails(): void
    {
        $commandTester = new CommandTester(
            $this->createCommand(new IndexStats(0, []), new IndexStatusReport([], 0, null))
        );

        $commandTester->execute(['--index' => 'nope']);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('No index "nope"', $commandTester->getDisplay());
    }

    private function makeStatus(
        string $indexName,
        IndexStatusState $state,
        int $pending = 0,
        int $withoutChecksum = 0,
        bool $copyRunning = false
    ): IndexStatus {
        return new IndexStatus(
            indexName: $indexName,
            label: $indexName === 'pimcore_asset' ? 'assets' : 'class Car (CAR)',
            state: $state,
            documentCount: 10,
            elementCount: 100,
            pendingEntries: $pending,
            documentsWithoutChecksum: $withoutChecksum,
            copyRunning: $copyRunning,
            mappingCurrent: true,
            settingsCurrent: true,
            rebuildQueuedAt: $pending > 0 ? 1 : null,
        );
    }

    /**
     * @param array<string, IndexStatus> $byName
     */
    private function createCommand(IndexStats $stats, IndexStatusReport $report, array $byName = []): StatusCommand
    {
        $statsService = $this->makeEmpty(IndexStatsServiceInterface::class, ['getStats' => $stats]);
        $statusService = $this->makeEmpty(IndexStatusServiceInterface::class, [
            'getAll' => $report,
            'getStatusByIndexName' => static function (string $name) use ($byName): IndexStatus {
                return $byName[$name] ?? throw new IndexStatusException(sprintf('No index "%s" is known.', $name));
            },
        ]);

        return new StatusCommand(
            $statsService,
            $this->getEmptyQueueRepository(),
            $statusService,
            $this->makeEmpty(IndexCountRepositoryInterface::class, ['countQueueEntries' => 0])
        );
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
