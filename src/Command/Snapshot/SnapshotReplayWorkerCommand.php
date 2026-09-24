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

namespace Pimcore\Bundle\GenericDataIndexBundle\Command\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkSenderInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Worker process of the parallel snapshot replay: reads bulk chunk paths from stdin, sends each
 * to the search engine, deletes the chunk file and answers "OK<TAB>path" — or
 * "ERR<TAB>path<TAB>message" and exits non-zero. Started and driven by
 * {@see \Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\WorkerPoolBulkDispatcher};
 * not meant to be run by hand.
 *
 * @internal
 */
final class SnapshotReplayWorkerCommand extends AbstractCommand
{
    /** @var resource */
    private $inputStream = STDIN;

    public function __construct(
        private readonly BulkSenderInterface $bulkSender,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    /**
     * @param resource $stream
     *
     * @internal for tests: read chunk paths from this stream instead of STDIN
     */
    public function setInputStream($stream): void
    {
        $this->inputStream = $stream;
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:snapshot:replay-worker')
            ->setHidden(true)
            ->setDescription(
                'Internal worker of generic-data-index:snapshot:import; reads bulk chunk paths from stdin.',
            )
            ->addArgument('index', InputArgument::REQUIRED, 'Short name of the index being replayed (for messages)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $index = (string) $input->getArgument('index');
        while (($line = fgets($this->inputStream)) !== false) {
            $path = rtrim($line, "\r\n");
            if ($path === '') {
                continue;
            }

            try {
                $body = file_get_contents($path);
                if ($body === false) {
                    throw new SnapshotImportException(sprintf('Cannot read bulk chunk "%s"', $path));
                }
                $this->bulkSender->send($body, $index);
            } catch (SnapshotImportException $e) {
                @unlink($path);
                $message = str_replace(["\r", "\n", "\t"], ' ', $e->getMessage());
                $output->writeln("ERR\t" . $path . "\t" . $message, OutputInterface::OUTPUT_RAW);

                return self::FAILURE;
            }
            @unlink($path);
            $output->writeln("OK\t" . $path, OutputInterface::OUTPUT_RAW);
        }

        return self::SUCCESS;
    }
}
