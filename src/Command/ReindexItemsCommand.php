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

namespace Pimcore\Bundle\GenericDataIndexBundle\Command;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\CommandAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class ReindexItemsCommand extends AbstractCommand
{
    use LockableTrait;

    public function __construct(
        private readonly ReindexServiceInterface $reindexService,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:reindex')
            ->setDescription(
                'Triggers native reindexing of existing indices.'
            );
    }

    /**
     * @throws CommandAlreadyRunningException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            throw new CommandAlreadyRunningException(
                'The command is already running in another process.'
            );
        }

        try {
            $output->writeln(
                '<info>Reindex all indices</info>',
                OutputInterface::VERBOSITY_NORMAL
            );

            $report = $this->reindexService->reindexAllIndicesWithReport();
            foreach ($report->getRebuilds() as $rebuild) {
                $output->writeln(sprintf(
                    '<comment>%s: %s; all its elements were queued for a rebuild from the database.</comment>',
                    $rebuild->getLabel(),
                    $this->describeRebuildReason($rebuild->getResult())
                ));
            }
            if (!$report->isEmpty()) {
                $output->writeln('<comment>Run the messenger workers to fill these indices.</comment>');
            }
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        } finally {
            $this->release();
        }

        $output->writeln(
            '<info>Finished</info>',
            OutputInterface::VERBOSITY_NORMAL
        );

        return self::SUCCESS;
    }

    private function describeRebuildReason(MappingUpdateResult $result): string
    {
        return match (true) {
            $result->isRecreated() => 'the index had to be created empty',
            $result->isOutcomeUnknown() => 'the mapping was applied by a custom index handler',
            default => 'fields left out of the copy: ' . implode(', ', $result->getStrippedPaths()),
        };
    }
}
