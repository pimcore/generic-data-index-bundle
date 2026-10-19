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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\CommandAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexPlan;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\DeploymentReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class DeploymentReindexCommand extends AbstractCommand
{
    use LockableTrait;

    private const OPTION_ALL = 'all';

    private const OPTION_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
        private readonly DeploymentReindexServiceInterface $deploymentReindexService,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:deployment:reindex')
            ->setDescription(
                'Updates index/mapping for all classDefinitions which changed without' .
                'deleting them. Afterwards are affected items added into the index queue.'
            )
            ->addOption(
                DeploymentReindexScope::CLASSES->value,
                null,
                InputOption::VALUE_NONE,
                'Check the class definition indices for mapping, index settings and data version changes'
            )
            ->addOption(
                DeploymentReindexScope::FOLDERS->value,
                null,
                InputOption::VALUE_NONE,
                'Check the data object folder index for mapping, index settings and data version changes'
            )
            ->addOption(
                DeploymentReindexScope::ASSETS->value,
                null,
                InputOption::VALUE_NONE,
                'Check the asset index for mapping, index settings and data version changes'
            )
            ->addOption(
                DeploymentReindexScope::DOCUMENTS->value,
                null,
                InputOption::VALUE_NONE,
                'Check the document index for mapping, index settings and data version changes'
            )
            ->addOption(
                self::OPTION_ALL,
                null,
                InputOption::VALUE_NONE,
                'Check all indices for mapping, index settings and data version changes'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Print the detected changes and the planned action per index without changing anything; ' .
                'needs one of the index options'
            )
            ->setHelp(
                'Without index options only the class definition indices are checked, by mapping checksum.' .
                PHP_EOL .
                'With index options (--all, --classes, --folders, --assets, --documents) only the selected ' .
                'indices are checked, by mapping checksum, index settings and data version. Values that were ' .
                'never stored are stored as the baseline on the first run, without a reindex.'
            );
    }

    /**
     * @throws CommandAlreadyRunningException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scopes = $this->getScopes($input);
        if ($scopes === [] && $input->getOption(self::OPTION_DRY_RUN)) {
            $output->writeln(
                '<error>--dry-run needs one of --all, --classes, --folders, --assets, --documents</error>'
            );

            return self::INVALID;
        }

        if (!$this->lock()) {
            throw new CommandAlreadyRunningException(
                'The command is already running in another process.'
            );
        }

        try {
            if ($scopes !== []) {
                return $this->executeForScopes($scopes, (bool) $input->getOption(self::OPTION_DRY_RUN), $output);
            }

            return $this->executeForClassDefinitions($output);
        } finally {
            $this->release();
        }
    }

    private function executeForClassDefinitions(OutputInterface $output): int
    {
        try {
            $updatedIds = [];
            $failed = false;
            $classesList = new ClassDefinition\Listing();
            $classes = $classesList->load();

            foreach ($classes as $classDefinition) {
                try {
                    $updated = $this->classDefinitionReindexService
                        ->reindexClassDefinition($classDefinition, true, true)
                    ;
                } catch (Exception $e) {
                    // Like the run with index options: report the class and go on with the
                    // remaining ones; the checksum of a failed class is not stored, so it is
                    // retried on the next run.
                    $failed = true;
                    $output->writeln(sprintf(
                        '<error>Updating class %s (%s) failed: %s</error>',
                        $classDefinition->getName(),
                        $classDefinition->getId(),
                        $e->getMessage()
                    ));

                    continue;
                }

                if ($updated) {
                    $updatedIds[] = $classDefinition->getId();
                }
            }

            if ($updatedIds !== []) {
                $output->writeln(
                    sprintf(
                        '<info>Updated following ClassDefinitions: [%s]</info>',
                        implode(', ', $updatedIds)
                    ),
                    OutputInterface::VERBOSITY_NORMAL
                );

                $output->writeln(
                    '<info>Dispatch queue messages</info>',
                    OutputInterface::VERBOSITY_NORMAL
                );

                $this->enqueueService->dispatchQueueMessages(true);
            } elseif (!$failed) {
                $output->writeln('<info>No updates needed - everything is up to date</info>', OutputInterface::VERBOSITY_NORMAL);
            }
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        if ($failed) {
            $output->writeln('<error>Finished with errors - failed classes are retried on the next run</error>');

            return self::FAILURE;
        }

        $output->writeln('<info>Finished</info>', OutputInterface::VERBOSITY_NORMAL);

        return self::SUCCESS;
    }

    /**
     * @return DeploymentReindexScope[]
     */
    private function getScopes(InputInterface $input): array
    {
        if ($input->getOption(self::OPTION_ALL)) {
            return DeploymentReindexScope::cases();
        }

        return array_values(array_filter(
            DeploymentReindexScope::cases(),
            static fn (DeploymentReindexScope $scope) => (bool) $input->getOption($scope->value)
        ));
    }

    /**
     * @param DeploymentReindexScope[] $scopes
     */
    private function executeForScopes(array $scopes, bool $dryRun, OutputInterface $output): int
    {
        try {
            $targets = $this->deploymentReindexService->getTargets($scopes);
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        $failed = false;
        $plans = [];
        foreach ($targets as $target) {
            try {
                $plans[] = $this->deploymentReindexService->plan($target);
            } catch (Exception $e) {
                $failed = true;
                $output->writeln(sprintf(
                    '<error>Checking %s failed: %s</error>',
                    $target->getLabel(),
                    $e->getMessage()
                ));
            }
        }

        $this->writePlans($plans, $output);

        if ($dryRun) {
            $output->writeln('<info>Dry run - nothing changed</info>');

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $enqueued = false;
        $baselineStored = false;
        foreach ($plans as $plan) {
            try {
                $enqueued = $this->deploymentReindexService->execute($plan) || $enqueued;
                $baselineStored = $baselineStored || $plan->isBaselineOnly();
            } catch (Exception $e) {
                $failed = true;
                $output->writeln(sprintf(
                    '<error>Updating %s failed: %s</error>',
                    $plan->getTarget()->getLabel(),
                    $e->getMessage()
                ));
            }
        }

        if ($enqueued) {
            $output->writeln('<info>Dispatch queue messages</info>');
            $this->enqueueService->dispatchQueueMessages(true);
        }

        if ($baselineStored) {
            $output->writeln(
                '<comment>Values that were not stored before were stored as the baseline without a reindex. ' .
                'If this deployment also changed the mapping of these indices, run generic-data-index:update:index ' .
                'once; if it changed index_settings, run generic-data-index:reindex once (not covering data object ' .
                'folders).</comment>'
            );
        }

        if ($failed) {
            $output->writeln('<error>Finished with errors - failed indices are retried on the next run</error>');

            return self::FAILURE;
        }

        $output->writeln('<info>Finished</info>');

        return self::SUCCESS;
    }

    /**
     * @param IndexPlan[] $plans
     */
    private function writePlans(array $plans, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['Index', 'Detected', 'Action']);
        foreach ($plans as $plan) {
            $table->addRow([
                $plan->getTarget()->getLabel(),
                implode(', ', $plan->getReasons()) ?: '-',
                $plan->getActionLabel(),
            ]);
        }
        $table->render();
    }
}
