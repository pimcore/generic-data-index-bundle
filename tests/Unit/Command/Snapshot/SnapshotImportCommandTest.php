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
use Pimcore\Bundle\GenericDataIndexBundle\Command\Snapshot\SnapshotImportCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotIncompatibleException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ClassCompatibility;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\CompatibilityReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportedIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotImporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SnapshotImportCommandTest extends Unit
{
    public function testUsesLatestSnapshotWhenNoNameGiven(): void
    {
        $storage = $this->makeEmpty(SnapshotStorageInterface::class, ['latestSnapshotName' => 'latest-one']);
        $importer = $this->makeEmpty(SnapshotImporterInterface::class, [
            'import' => function (SnapshotStorageInterface $s, string $name, ImportOptions $options): ImportResult {
                $this->assertSame('latest-one', $name);

                return $this->importResult($name, [new ImportedIndex('asset', 'pimcore_asset', 5, 5)]);
            },
        ]);
        $tester = new CommandTester($this->command($storage, $importer));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('asset', $tester->getDisplay());
    }

    public function testFailsWhenNoSnapshotExists(): void
    {
        $storage = $this->makeEmpty(SnapshotStorageInterface::class, ['latestSnapshotName' => null]);
        $tester = new CommandTester($this->command($storage, $this->makeEmpty(SnapshotImporterInterface::class)));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('No snapshot found', $tester->getDisplay());
    }

    public function testFromPathBuildsLocalStorageAndPassesOptions(): void
    {
        $dir = sys_get_temp_dir() . '/gdi-snapshot-cmd-' . uniqid();
        mkdir($dir . '/snap', 0777, true);
        file_put_contents($dir . '/snap/manifest.json', json_encode((new Manifest('2026-09-10T00:00:00+00:00', 'dev', 'dev', 'openSearch', 'pimcore_', 0, 0, 0, [], []))->toArray()));
        $importer = $this->makeEmpty(SnapshotImporterInterface::class, [
            'import' => function (SnapshotStorageInterface $storage, string $name, ImportOptions $options): ImportResult {
                $this->assertInstanceOf(SnapshotStorage::class, $storage);
                $this->assertTrue($storage->hasSnapshot('snap'));
                $this->assertTrue($options->force);
                $this->assertSame(['asset', 'data-object_simple'], $options->only);

                return $this->importResult($name, []);
            },
        ]);
        $tester = new CommandTester($this->command($this->makeEmpty(SnapshotStorageInterface::class), $importer));

        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => 'snap', '--from-path' => $dir, '--force' => true, '--only' => 'asset, data-object_simple']));
    }

    public function testIncompleteIndexIsFailure(): void
    {
        $importer = $this->makeEmpty(SnapshotImporterInterface::class, [
            'import' => fn (SnapshotStorageInterface $s, string $name): ImportResult => $this->importResult($name, [new ImportedIndex('asset', 'pimcore_asset', 5, 4)]),
        ]);
        $tester = new CommandTester($this->command($this->makeEmpty(SnapshotStorageInterface::class, ['latestSnapshotName' => 'x']), $importer));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('5', $tester->getDisplay());
    }

    public function testIncompatibleExceptionPrintsClassIdsAndForceHint(): void
    {
        $report = new CompatibilityReport([new ClassCompatibility('PR', 'Product', ClassCompatibilityStatus::INCOMPATIBLE, 1, 2, 3)], []);
        $importer = $this->makeEmpty(SnapshotImporterInterface::class, [
            'import' => static function () use ($report): never {
                throw new SnapshotIncompatibleException('mismatch', $report);
            },
        ]);
        $tester = new CommandTester($this->command($this->makeEmpty(SnapshotStorageInterface::class, ['latestSnapshotName' => 'x']), $importer));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Product', $tester->getDisplay());
        $this->assertStringContainsString('--force', $tester->getDisplay());
    }

    public function testNonZeroQueueCountWarnsButStillImports(): void
    {
        $importer = $this->makeEmpty(SnapshotImporterInterface::class, [
            'import' => fn (SnapshotStorageInterface $s, string $name): ImportResult => $this->importResult($name, [new ImportedIndex('asset', 'pimcore_asset', 5, 5)]),
        ]);
        $tester = new CommandTester($this->command(
            $this->makeEmpty(SnapshotStorageInterface::class, ['latestSnapshotName' => 'x']),
            $importer,
            42
        ));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Stop messenger consumers', $display);
        $this->assertStringContainsString('42', $display);
    }

    private function command(SnapshotStorageInterface $storage, SnapshotImporterInterface $importer, int $queueCount = 0): SnapshotImportCommand
    {
        return new SnapshotImportCommand($storage, $importer, $this->makeEmpty(IndexStatsServiceInterface::class, [
            'getStats' => new IndexStats($queueCount, []),
        ]));
    }

    private function importResult(string $name, array $imported): ImportResult
    {
        $manifest = new Manifest('2026-09-10T00:00:00+00:00', 'dev', 'dev', 'openSearch', 'pimcore_', 0, 0, 0, [], []);

        return new ImportResult($name, $manifest, new CompatibilityReport([], []), $imported, [], [], false);
    }
}
