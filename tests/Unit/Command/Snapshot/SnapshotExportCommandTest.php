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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Command\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Command\Snapshot\SnapshotExportCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotLock;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class SnapshotExportCommandTest extends Unit
{
    public function testPrintsTableAndReturnsSuccess(): void
    {
        $manifest = new Manifest('2026-09-10T00:00:00+00:00', 'dev', 'dev', 'openSearch', 'pimcore_', 0, 4, 12, [], [
            new ManifestIndex('data-object_simple', 'dataObject', 'SI', 'pimcore_data-object_simple-odd', 3, 'data-object_simple.ndjson.gz', 120, 'abc'),
        ]);
        $exporter = $this->makeEmpty(SnapshotExporterInterface::class, [
            'export' => function (SnapshotStorageInterface $storage, string $name, ExportOptions $options) use ($manifest): ExportResult {
                $this->assertSame('nightly', $name);

                return new ExportResult($name, $manifest, false);
            },
        ]);
        $tester = new CommandTester($this->command($exporter));

        $exitCode = $tester->execute(['--name' => 'nightly']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('data-object_simple', $display);
        $this->assertStringContainsString('queue entries before: 0', $display);
        $this->assertStringNotContainsString('not idle', $display);
    }

    public function testNonZeroQueueCountBeforeNotesTheIndexIsNotIdle(): void
    {
        $manifest = new Manifest('2026-09-10T00:00:00+00:00', 'dev', 'dev', 'openSearch', 'pimcore_', 3, 0, 0, [], []);
        $exporter = $this->makeEmpty(SnapshotExporterInterface::class, [
            'export' => static fn (SnapshotStorageInterface $storage, string $name, ExportOptions $options): ExportResult => new ExportResult($name, $manifest, false),
        ]);
        $tester = new CommandTester($this->command($exporter));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('not idle', $tester->getDisplay());
    }

    public function testDefaultNameIsUtcTimestamp(): void
    {
        $exporter = $this->makeEmpty(SnapshotExporterInterface::class, [
            'export' => function (SnapshotStorageInterface $storage, string $name, ExportOptions $options): ExportResult {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-\d{2}Z$/', $name);

                $manifest = new Manifest('x', 'dev', 'dev', 'openSearch', 'pimcore_', 0, 0, 0, [], []);

                return new ExportResult($name, $manifest, false);
            },
        ]);

        $this->assertSame(Command::SUCCESS, (new CommandTester($this->command($exporter)))->execute([]));
    }

    public function testExceptionBecomesFailureExitCode(): void
    {
        $exporter = $this->makeEmpty(SnapshotExporterInterface::class, [
            'export' => static function (): never {
                throw new SnapshotExportException('queue too deep');
            },
        ]);
        $tester = new CommandTester($this->command($exporter));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('queue too deep', $tester->getDisplay());
    }

    private function command(SnapshotExporterInterface $exporter, ?LockFactory $lockFactory = null): SnapshotExportCommand
    {
        return new SnapshotExportCommand(
            $this->makeEmpty(SnapshotStorageInterface::class),
            $exporter,
            $lockFactory ?? new LockFactory(new InMemoryStore()),
        );
    }

    public function testRefusesToRunWhileTheSnapshotLockIsHeld(): void
    {
        // The lock comes from the installation's lock store (framework.lock), not from a
        // host-local flock: with a shared store this also excludes a run on another node.
        $lockFactory = new LockFactory(new InMemoryStore());
        $heldElsewhere = SnapshotLock::create($lockFactory);
        $this->assertTrue($heldElsewhere->acquire());
        $exporter = $this->makeEmpty(SnapshotExporterInterface::class, [
            'export' => static function (): never {
                throw new RuntimeException('must not export while another run holds the lock');
            },
        ]);
        $tester = new CommandTester($this->command($exporter, $lockFactory));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('already running', $tester->getDisplay());
    }

    public function testReleasesTheLockWhenDone(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $exporter = $this->makeEmpty(SnapshotExporterInterface::class, [
            'export' => static function (): never {
                throw new RuntimeException('boom');
            },
        ]);
        (new CommandTester($this->command($exporter, $lockFactory)))->execute([]);

        $this->assertTrue(
            SnapshotLock::create($lockFactory)->acquire(),
            'the lock is released even when the export failed',
        );
    }
}
