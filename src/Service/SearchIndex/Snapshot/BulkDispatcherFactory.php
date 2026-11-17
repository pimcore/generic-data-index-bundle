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

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Creates the worker pool that sends the bulk chunks of one index: import_workers worker
 * processes of the running application, started in the same environment and debug mode.
 *
 * @internal
 */
final class BulkDispatcherFactory
{
    public function __construct(
        private readonly int $workers,
        private readonly string $projectDir,
        private readonly string $environment,
        private readonly bool $debug,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?BulkSenderInterface $bulkSender = null,
    ) {
    }

    /**
     * The worker pool, or - for a process whose descriptors a pipe can no longer be selected on,
     * see {@see SubprocessPipeSupport} - the in-process dispatcher, which is slower but is the
     * difference between a slow import and an import that hangs with nothing to show for it.
     */
    public function create(): BulkDispatcherInterface
    {
        if ($this->bulkSender !== null && !SubprocessPipeSupport::pipesAreUsable()) {
            $this->logger?->warning(
                'Replaying the snapshot in this process: it holds too many open files for a pipe to a '
                . 'worker process to be usable (stream_select() is limited to 1024 descriptors). The import '
                . 'runs one bulk request at a time instead of ' . $this->workers . '.'
            );

            return new InProcessBulkDispatcher($this->bulkSender, $this->logger);
        }

        return new WorkerPoolBulkDispatcher($this->workerCommand(), $this->workers, $this->logger);
    }

    /**
     * @return string[] argv of one worker process, without the index name the dispatcher appends
     */
    public function workerCommand(): array
    {
        $php = (new PhpExecutableFinder())->find(false);
        $command = [
            $php !== false ? $php : 'php',
            $this->projectDir . '/bin/console',
            'generic-data-index:snapshot:replay-worker',
            '--env=' . $this->environment,
            '--no-interaction',
        ];
        if (!$this->debug) {
            $command[] = '--no-debug';
        }

        return $command;
    }
}
