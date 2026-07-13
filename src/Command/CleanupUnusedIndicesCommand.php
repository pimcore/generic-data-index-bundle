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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\UnusedIndexCleanupService;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class CleanupUnusedIndicesCommand extends AbstractCommand
{
    use LockableTrait;

    private const OPTION_DRY_RUN = 'dry-run';

    private const OPTION_MIN_AGE = 'min-age';

    public function __construct(
        private readonly UnusedIndexCleanupService $unusedIndexCleanupService,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:cleanup:unused-indices')
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'List unused indices without deleting them.'
            )
            ->addOption(
                self::OPTION_MIN_AGE,
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum age in seconds an index must have before it is considered unused. '
                . 'Set to 0 to disable the age guard.',
                (string) UnusedIndexCleanupService::DEFAULT_MIN_AGE_SECONDS
            )
            ->setDescription(
                'Deletes managed Generic Data Index indices with the configured index prefix '
                . 'and a -odd/-even suffix that are not referenced by any alias.'
            )
            ->setHelp(
                'This command only targets managed Generic Data Index indices that use the '
                . 'configured index prefix and end with -odd or -even. It does not consider other indices.'
                . PHP_EOL
                . 'A reindex creates and populates the new -odd/-even index before attaching it to its '
                . 'alias, so during that window the new index is not referenced by any alias. To avoid '
                . 'deleting an index that is actively being built, indices younger than --min-age seconds '
                . '(default: 86400) are never deleted. Only lower this threshold or disable it '
                . '(--min-age=0) when no reindex is or was recently running.'
            );
    }

    /**
     * @throws CommandAlreadyRunningException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            throw new CommandAlreadyRunningException(
                'The command is already running in another process.'
            );
        }

        try {
            $dryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);

            $minAge = $input->getOption(self::OPTION_MIN_AGE);
            if (!is_numeric($minAge) || (int) $minAge < 0) {
                $output->writeln('<error>The --min-age option must be a non-negative number of seconds.</error>');

                return self::FAILURE;
            }

            $unusedIndices = $this->unusedIndexCleanupService->cleanupUnusedIndices($dryRun, (int) $minAge);

            if (empty($unusedIndices)) {
                $output->writeln('<info>No unused indices found.</info>');

                return self::SUCCESS;
            }

            $output->writeln('<info>Unused indices:</info>');
            foreach ($unusedIndices as $indexName) {
                $output->writeln(sprintf(' - %s', $indexName));
            }

            if ($dryRun) {
                $output->writeln(
                    sprintf('<comment>Dry run: %d indices would be deleted.</comment>', count($unusedIndices))
                );
            } else {
                $output->writeln(
                    sprintf('<info>Deleted %d unused indices.</info>', count($unusedIndices))
                );
            }
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        } finally {
            $this->release();
        }

        return self::SUCCESS;
    }
}
