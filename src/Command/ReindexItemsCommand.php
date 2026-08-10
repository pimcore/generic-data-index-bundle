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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\CommandAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class ReindexItemsCommand extends AbstractCommand
{
    use LockableTrait;

    public function __construct(
        private readonly ReindexServiceInterface $reindexService,
        private readonly CalculatedFieldsIndexModeResolverInterface $calculatedFieldsIndexModeResolver,
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
            )
            ->addOption(
                'calculated-fields-mode',
                null,
                InputOption::VALUE_REQUIRED,
                'Override the calculated_fields_index_mode for this run (live|query_store). '
                . 'Use "live" to refresh calculated field values in the index while the '
                . 'configured mode is query_store.'
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
            $modeOption = $input->getOption('calculated-fields-mode');
            if ($modeOption !== null) {
                $mode = CalculatedFieldsIndexMode::tryFrom($modeOption);
                if ($mode === null) {
                    $output->writeln(sprintf(
                        '<error>Invalid --calculated-fields-mode "%s". Valid values: %s</error>',
                        $modeOption,
                        implode(', ', array_column(CalculatedFieldsIndexMode::cases(), 'value'))
                    ));

                    return self::INVALID;
                }

                $this->calculatedFieldsIndexModeResolver->overrideMode($mode);

                // Value extraction happens in the queue worker processes, not in this
                // command - the override only covers work done synchronously here.
                $output->writeln(sprintf(
                    '<comment>Note: queue workers extract the element values. To apply the mode '
                    . 'override there, run the workers with %s=%s until the queue has drained.</comment>',
                    CalculatedFieldsIndexModeResolverInterface::ENV_VAR,
                    $mode->value
                ));
            }

            $output->writeln(
                '<info>Reindex all indices</info>',
                OutputInterface::VERBOSITY_NORMAL
            );

            $this->reindexService->reindexAllIndices();
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
}
