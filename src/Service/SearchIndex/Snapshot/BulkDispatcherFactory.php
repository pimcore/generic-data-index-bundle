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
 * Chooses how the bulk chunks of an index are sent: from the importing process itself with one
 * worker, or through a pool of worker processes so that several bulk requests are in flight at
 * once (import_workers > 1).
 *
 * @internal
 */
final class BulkDispatcherFactory
{
    public function __construct(
        private readonly BulkSenderInterface $bulkSender,
        private readonly int $workers,
        private readonly string $projectDir,
        private readonly string $environment,
        private readonly bool $debug,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function create(): BulkDispatcherInterface
    {
        if ($this->workers <= 1) {
            return new InProcessBulkDispatcher($this->bulkSender, $this->logger);
        }

        return new WorkerPoolBulkDispatcher($this->workerCommand(), $this->workers, $this->logger);
    }

    public function workers(): int
    {
        return $this->workers;
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
