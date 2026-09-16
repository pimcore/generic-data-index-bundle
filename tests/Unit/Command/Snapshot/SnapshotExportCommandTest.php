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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SnapshotExportCommandTest extends Unit
{
    public function testPrintsTableAndReturnsSuccess(): void
    {
        $manifest = new Manifest('2026-09-10T00:00:00+00:00', 'dev', 'dev', 'openSearch', 'pimcore_', 3, 4, 12, [], [
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
        $this->assertStringContainsString('queue entries before: 3', $display);
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

    private function command(SnapshotExporterInterface $exporter): SnapshotExportCommand
    {
        return new SnapshotExportCommand($this->makeEmpty(SnapshotStorageInterface::class), $exporter);
    }
}
