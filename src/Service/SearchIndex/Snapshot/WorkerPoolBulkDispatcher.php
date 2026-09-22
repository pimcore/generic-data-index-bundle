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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\BulkChunk;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Sends chunks through N long-running worker processes so that several bulk requests are in
 * flight at once; the search engine indexes them on its write threads in parallel. Each worker
 * boots the application once, then reads chunk paths from stdin and answers one line per chunk:
 * "OK<TAB>path" or "ERR<TAB>path<TAB>message". At most two chunks are queued per worker, which
 * bounds the temp-disk footprint of unsent chunks to 2 × workers × bulk_bytes.
 *
 * @internal
 */
final class WorkerPoolBulkDispatcher implements BulkDispatcherInterface
{
    private const MAX_QUEUED_PER_WORKER = 2;

    private const POLL_MICROSECONDS = 20_000;

    /** @var Process[] */
    private array $processes = [];

    /** @var InputStream[] */
    private array $inputs = [];

    /** @var array<int, array<string, BulkChunk>> chunks handed to a worker and not yet acknowledged, by worker */
    private array $inFlight = [];

    /** @var string[] unread stdout remainder per worker */
    private array $buffers = [];

    private string $indexShortName = '';

    /**
     * @param string[] $workerCommand argv of one worker process (the index name is appended by start())
     */
    public function __construct(
        private readonly array $workerCommand,
        private readonly int $workers,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function start(IndexTarget $target): void
    {
        $this->indexShortName = $target->shortName;
        for ($i = 0; $i < $this->workers; $i++) {
            $input = new InputStream();
            $process = new Process([...$this->workerCommand, $target->shortName]);
            $process->setTimeout(null);
            $process->setInput($input);

            try {
                $process->start();
            } catch (ProcessException $e) {
                $this->abort();

                throw new SnapshotImportException(
                    sprintf('Cannot start snapshot replay worker: %s', $e->getMessage()),
                    0,
                    $e,
                );
            }
            $this->processes[$i] = $process;
            $this->inputs[$i] = $input;
            $this->inFlight[$i] = [];
            $this->buffers[$i] = '';
        }
    }

    public function dispatch(BulkChunk $chunk): void
    {
        $worker = $this->waitForFreeWorker();
        $this->inFlight[$worker][$chunk->path] = $chunk;
        $this->logger?->debug('Snapshot import bulk', [
            'index' => $this->indexShortName,
            'worker' => $worker,
            'documents' => $chunk->documents,
            'bytes' => $chunk->bytes,
        ]);
        $this->inputs[$worker]->write($chunk->path . "\n");
    }

    public function finish(): void
    {
        foreach ($this->inputs as $input) {
            $input->close();
        }
        while ($this->pending() > 0 || $this->anyRunning()) {
            $this->pump();
            if ($this->pending() === 0 && !$this->anyRunning()) {
                break;
            }
            usleep(self::POLL_MICROSECONDS);
        }
        foreach ($this->processes as $i => $process) {
            $process->wait();
            if (!$process->isSuccessful()) {
                $this->abort();

                throw new SnapshotImportException(sprintf(
                    'Snapshot replay worker %d exited with code %d for index "%s": %s',
                    $i,
                    (int) $process->getExitCode(),
                    $this->indexShortName,
                    trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : 'no error output',
                ));
            }
        }
        $this->processes = [];
    }

    public function abort(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(5);
            }
        }
        foreach ($this->inFlight as $chunks) {
            foreach ($chunks as $chunk) {
                @unlink($chunk->path);
            }
        }
        $this->processes = [];
        $this->inFlight = [];
    }

    /**
     * @throws SnapshotImportException
     */
    private function waitForFreeWorker(): int
    {
        while (true) {
            $this->pump();
            $best = null;
            foreach ($this->inFlight as $i => $chunks) {
                $hasRoom = count($chunks) < self::MAX_QUEUED_PER_WORKER;
                if ($hasRoom && ($best === null || count($chunks) < count($this->inFlight[$best]))) {
                    $best = $i;
                }
            }
            if ($best !== null) {
                return $best;
            }
            usleep(self::POLL_MICROSECONDS);
        }
    }

    /**
     * Reads the workers' answers; a worker that died without acknowledging its chunks fails the replay.
     *
     * @throws SnapshotImportException
     */
    private function pump(): void
    {
        foreach ($this->processes as $i => $process) {
            $this->buffers[$i] .= $process->getIncrementalOutput();
            while (($newline = strpos($this->buffers[$i], "\n")) !== false) {
                $line = substr($this->buffers[$i], 0, $newline);
                $this->buffers[$i] = substr($this->buffers[$i], $newline + 1);
                $this->handleAnswer($i, $line);
            }
            if (!$process->isRunning() && $this->inFlight[$i] !== []) {
                $this->abort();

                throw new SnapshotImportException(sprintf(
                    'Snapshot replay worker %d died with exit code %d while %d chunk(s) of index "%s" were pending: %s',
                    $i,
                    (int) $process->getExitCode(),
                    count($this->inFlight[$i] ?? []),
                    $this->indexShortName,
                    trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : 'no error output',
                ));
            }
        }
    }

    /**
     * @throws SnapshotImportException
     */
    private function handleAnswer(int $worker, string $line): void
    {
        $parts = explode("\t", $line, 3);
        if (($parts[0] ?? '') === 'OK' && isset($parts[1])) {
            unset($this->inFlight[$worker][$parts[1]]);

            return;
        }
        if (($parts[0] ?? '') === 'ERR') {
            $this->abort();

            throw new SnapshotImportException(sprintf(
                'Import of index "%s" failed in replay worker %d: %s',
                $this->indexShortName,
                $worker,
                $parts[2] ?? $line,
            ));
        }
        $this->logger?->warning('Unexpected output from snapshot replay worker', [
            'worker' => $worker,
            'line' => $line,
        ]);
    }

    private function pending(): int
    {
        return array_sum(array_map('count', $this->inFlight));
    }

    private function anyRunning(): bool
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                return true;
            }
        }

        return false;
    }
}
