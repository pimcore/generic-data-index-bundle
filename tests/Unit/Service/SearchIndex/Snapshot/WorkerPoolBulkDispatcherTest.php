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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\BulkChunk;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\WorkerPoolBulkDispatcher;

final class WorkerPoolBulkDispatcherTest extends Unit
{
    /** @var string[] */
    private array $paths = [];

    protected function _after(): void
    {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->paths = [];
    }

    public function testWorkerPoolDistributesChunksAcrossWorkersAndWaitsForEveryAcknowledgement(): void
    {
        // fake worker: acknowledges each path after "sending" it (recording which worker took it)
        $log = tempnam(sys_get_temp_dir(), 'gdi-pool-');
        $this->paths[] = $log;
        $dispatcher = new WorkerPoolBulkDispatcher($this->fakeWorker(
            'usleep(20000); file_put_contents($argv[1], getmypid() . " " . $path . "\n", FILE_APPEND);'
            . ' unlink($path); echo "OK\t$path\n";',
            [$log],
        ), 3);
        $chunks = array_map(fn (int $i) => $this->chunk("chunk $i"), range(1, 9));

        $dispatcher->start(new IndexTarget('asset', 'pimcore_asset', 'asset'));
        foreach ($chunks as $chunk) {
            $dispatcher->dispatch($chunk);
        }
        $dispatcher->finish();

        $lines = array_filter(explode("\n", (string) file_get_contents($log)));
        $this->assertCount(9, $lines, 'every chunk was processed exactly once');
        $workerPids = array_unique(array_map(static fn (string $l) => explode(' ', $l)[0], $lines));
        $this->assertCount(3, $workerPids, 'all three workers took part');
        foreach ($chunks as $chunk) {
            $this->assertFileDoesNotExist($chunk->getPath());
        }
    }

    public function testWorkerPoolReportsAWorkerErrorAndCleansUp(): void
    {
        $dispatcher = new WorkerPoolBulkDispatcher($this->fakeWorker(
            'echo "ERR\t$path\tmapper_parsing_exception failed to parse\n"; exit(1);',
        ), 2);
        $chunks = [$this->chunk('a'), $this->chunk('b'), $this->chunk('c')];
        $dispatcher->start(new IndexTarget('asset', 'pimcore_asset', 'asset'));

        // the failure can surface from any dispatch() call, depending on scheduling; chunks the
        // loop never handed over stay with the caller, so only attempted ones are asserted
        $attempted = [];

        try {
            foreach ($chunks as $chunk) {
                $attempted[] = $chunk;
                $dispatcher->dispatch($chunk);
            }
            $dispatcher->finish();
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('asset', $e->getMessage());
            $this->assertStringContainsString('mapper_parsing_exception failed to parse', $e->getMessage());
        }
        foreach ($attempted as $chunk) {
            $this->assertFileDoesNotExist($chunk->getPath(), 'every chunk handed to the pool is removed on abort');
        }
    }

    public function testWorkerPoolRemovesTheChunkWhoseDispatchSurfacesAnEarlierFailure(): void
    {
        // one worker, which fails its first chunk; the failure surfaces from a later dispatch()
        // (which one depends on scheduling), and that call's chunk is not tracked by the pool yet
        $dispatcher = new WorkerPoolBulkDispatcher($this->fakeWorker('echo "ERR\t$path\tboom\n"; exit(1);'), 1);
        $chunks = array_map(fn (string $c) => $this->chunk($c), ['a', 'b', 'c', 'd']);
        $attempted = [];
        $dispatcher->start(new IndexTarget('asset', 'pimcore_asset', 'asset'));

        try {
            foreach ($chunks as $chunk) {
                $attempted[] = $chunk;
                $dispatcher->dispatch($chunk);
                usleep(100_000);
            }
            $dispatcher->finish();
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
        }
        $this->assertGreaterThan(1, count($attempted), 'the failure surfaced from a later dispatch() call');
        foreach ($attempted as $chunk) {
            $this->assertFileDoesNotExist(
                $chunk->getPath(),
                'including the chunk of the call that surfaced the failure',
            );
        }
    }

    public function testAWorkerThatDiesReportsHowManyChunksWereLost(): void
    {
        // the worker takes the chunk and dies without answering: one chunk is lost
        $dispatcher = new WorkerPoolBulkDispatcher($this->fakeWorker('exit(3);'), 1);
        $dispatcher->start(new IndexTarget('asset', 'pimcore_asset', 'asset'));

        try {
            $dispatcher->dispatch($this->chunk('a'));
            $dispatcher->finish();
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('while 1 chunk(s)', $e->getMessage());
        }
    }

    public function testAWorkerThatExitsRightAfterItsLastAnswerIsNotReportedAsDead(): void
    {
        // answering and exiting without waiting for stdin to close: the answer written before
        // the exit must still be processed, not taken for a worker that died with pending chunks
        $dispatcher = new WorkerPoolBulkDispatcher(
            $this->fakeWorker('unlink($path); echo "OK\t$path\n"; exit(0);'),
            1,
        );
        $chunk = $this->chunk('a');
        $dispatcher->start(new IndexTarget('asset', 'pimcore_asset', 'asset'));

        $dispatcher->dispatch($chunk);
        $dispatcher->finish();

        $this->assertFileDoesNotExist($chunk->getPath());
    }

    public function testWorkerPoolReportsAWorkerThatDiesWithoutAnswering(): void
    {
        $dispatcher = new WorkerPoolBulkDispatcher($this->fakeWorker('fwrite(STDERR, "segfault-ish"); exit(3);'), 1);
        $dispatcher->start(new IndexTarget('asset', 'pimcore_asset', 'asset'));

        try {
            $dispatcher->dispatch($this->chunk('a'));
            $dispatcher->finish();
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('exit code 3', $e->getMessage());
            $this->assertStringContainsString('segfault-ish', $e->getMessage());
        }
    }

    /**
     * A worker process as an inline PHP script: reads chunk paths from stdin and runs $perPath
     * for each (with $path set); extra argv is available as $argv[1..].
     *
     * @return string[] argv
     */
    private function fakeWorker(string $perPath, array $extraArgs = []): array
    {
        $script = 'while (($path = fgets(STDIN)) !== false) {'
            . ' $path = rtrim($path, "\n"); if ($path === "") { continue; } '
            . $perPath . ' } exit(0);';

        // the dispatcher appends the index name as the last argument; the script ignores it
        return [PHP_BINARY, '-r', $script, '--', ...$extraArgs];
    }

    private function chunk(string $content): BulkChunk
    {
        $path = tempnam(sys_get_temp_dir(), 'gdi-chunk-');
        file_put_contents($path, $content);
        $this->paths[] = $path;

        return new BulkChunk($path, 1, strlen($content));
    }
}
