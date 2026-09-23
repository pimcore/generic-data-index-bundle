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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkChunkWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkDispatcherFactory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentReplayer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\ReplayIndexSettingsInterface;
use Psr\Log\NullLogger;

/**
 * Drives the replay through the real worker pool, with a fake application in a temporary
 * project directory: its bin/console acknowledges (or rejects) every chunk it receives and
 * records the chunk bodies, so the test sees exactly what would have been sent.
 */
final class DocumentReplayerTest extends Unit
{
    /** @var string[] */
    private array $paths = [];

    private string $projectDir;

    /** @var string[] disableRefresh/restoreRefresh calls on the settings service, in order */
    private array $settingsCalls = [];

    protected function _before(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/gdi-replayer-test-' . bin2hex(random_bytes(4));
        mkdir($this->projectDir . '/bin', 0o777, true);
        file_put_contents($this->projectDir . '/bin/console', <<<'PHP'
            <?php
            $dir = dirname(__DIR__);
            $mode = trim((string) @file_get_contents($dir . '/mode'));
            while (($path = fgets(STDIN)) !== false) {
                $path = rtrim($path, "\n");
                if ($path === '') {
                    continue;
                }
                if ($mode === 'err') {
                    unlink($path);
                    echo "ERR\t$path\tmapper_parsing_exception failed to parse\n";
                    exit(1);
                }
                file_put_contents($dir . '/bulk.log', file_get_contents($path) . "---\n", FILE_APPEND | LOCK_EX);
                unlink($path);
                echo "OK\t$path\n";
            }
            PHP);
        $this->settingsCalls = [];
    }

    protected function _after(): void
    {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->paths = [];
        foreach (['bin/console', 'mode', 'bulk.log'] as $file) {
            @unlink($this->projectDir . '/' . $file);
        }
        @rmdir($this->projectDir . '/bin');
        @rmdir($this->projectDir);
    }

    public function testSendsEveryChunkThroughTheWorkersAndLeavesNoChunkFilesBehind(): void
    {
        $path = $this->writeDocuments([
            ['system_fields' => ['id' => 1], 'standard_fields' => ['pad' => str_repeat('a', 20)]],
            ['system_fields' => ['id' => 2], 'standard_fields' => ['pad' => str_repeat('b', 20)]],
            ['system_fields' => ['id' => 3], 'standard_fields' => ['pad' => str_repeat('c', 20)]],
        ]);

        $this->replay($path, bulkSize: 2, workers: 2);

        $bodies = $this->sentBodies();
        $this->assertCount(2, $bodies, 'two documents per bulk, then the rest');
        $sent = implode('', $bodies);
        foreach ([1, 2, 3] as $id) {
            $this->assertStringContainsString('"_index":"pimcore_asset","_id":' . $id . '}', $sent);
        }
        $leftovers = glob(sys_get_temp_dir() . '/gdi-snapshot-bulk-*') ?: [];
        $this->assertSame([], $leftovers, 'chunk files are deleted after sending');
    }

    public function testAWorkerErrorAbortsTheReplay(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 1]], ['system_fields' => ['id' => 2]]]);
        file_put_contents($this->projectDir . '/mode', 'err');

        try {
            $this->replay($path, bulkSize: 1000, workers: 1);
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('asset', $e->getMessage());
            $this->assertStringContainsString('mapper_parsing_exception', $e->getMessage());
        }
    }

    public function testRefreshIsDisabledForTheReplayAndRestoredAfterwards(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 1]]]);

        $this->replay($path, bulkSize: 1000, workers: 1);

        $this->assertSame(['disable pimcore_asset', 'restore pimcore_asset 30s'], $this->settingsCalls);
    }

    public function testRefreshIsRestoredWhenTheReplayFails(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 1]]]);
        file_put_contents($this->projectDir . '/mode', 'err');

        try {
            $this->replay($path, bulkSize: 1000, workers: 1);
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException) {
            $this->assertSame(['disable pimcore_asset', 'restore pimcore_asset 30s'], $this->settingsCalls);
        }
    }

    public function testInvalidDocumentsAbortTheReplayBeforeAnythingIsSent(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 'nope']]]);

        try {
            $this->replay($path, bulkSize: 1000, workers: 1);
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('Document without integer system_fields.id', $e->getMessage());
            $this->assertSame([], $this->sentBodies());
        }
    }

    private function replay(string $path, int $bulkSize, int $workers): void
    {
        $settings = $this->makeEmpty(ReplayIndexSettingsInterface::class, [
            'disableRefresh' => function (string $index): ?string {
                $this->settingsCalls[] = 'disable ' . $index;

                return '30s';
            },
            'restoreRefresh' => function (string $index, ?string $previous): void {
                $this->settingsCalls[] = 'restore ' . $index . ' ' . $previous;
            },
        ]);
        $replayer = new DocumentReplayer(
            new BulkChunkWriter(new DocumentFileReader(), $bulkSize, 1024 * 1024),
            new BulkDispatcherFactory($workers, $this->projectDir, 'test', false),
            $settings,
        );
        $replayer->setLogger(new NullLogger());
        $target = new IndexTarget('asset', 'pimcore_asset', 'asset');
        $entry = new ManifestIndex(
            'asset', 'asset', null, 'pimcore_asset-odd', 3, 'asset.ndjson.gz', 1, str_repeat('0', 64),
        );

        $replayer->replay($target, $entry, $path);
    }

    /** @return string[] bulk bodies the fake workers received */
    private function sentBodies(): array
    {
        $log = (string) @file_get_contents($this->projectDir . '/bulk.log');

        return array_values(array_filter(explode("---\n", $log), static fn (string $b) => $b !== ''));
    }

    private function writeDocuments(array $documents): string
    {
        $writer = DocumentFileWriter::createTemporary();
        foreach ($documents as $document) {
            $writer->write($document);
        }
        $written = $writer->finish();
        $this->paths[] = $written->path;

        return $written->path;
    }
}
