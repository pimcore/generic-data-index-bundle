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

namespace Pimcore\Bundle\GenericDataIndexBundle\Command\Update;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\CommandAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IdNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\CalculatedFieldsModeOptionTrait;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class IndexUpdateCommand extends AbstractCommand
{
    use CalculatedFieldsModeOptionTrait;
    use LockableTrait;

    private const OPTION_CLASS_DEFINITION_ID = 'class-definition-id';

    private const OPTION_UPDATE_ASSET_INDEX = 'update-asset-index';

    private const OPTION_RECREATE_INDEX = 'recreate_index';

    private const UPDATE_GLOBAL_ALIASES_ONLY = 'update-global-aliases-only';

    private IndexUpdateServiceInterface $indexUpdateService;

    private EnqueueServiceInterface $enqueueService;

    private GlobalIndexAliasServiceInterface $globalIndexAliasService;

    #[Required]
    public function setIndexUpdateService(IndexUpdateServiceInterface $indexUpdateService): void
    {
        $this->indexUpdateService = $indexUpdateService;
    }

    #[Required]
    public function setEnqueueService(EnqueueServiceInterface $enqueueService): void
    {
        $this->enqueueService = $enqueueService;
    }

    #[Required]
    public function setGlobalIndexAliasService(GlobalIndexAliasServiceInterface $globalIndexAliasService): void
    {
        $this->globalIndexAliasService = $globalIndexAliasService;
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:update:index')
            ->addOption(
                self::OPTION_CLASS_DEFINITION_ID,
                'c',
                InputOption::VALUE_OPTIONAL,
                'Update mapping and data for specific data object classDefinition',
                null
            )
            ->addOption(
                self::OPTION_UPDATE_ASSET_INDEX,
                'a',
                InputOption::VALUE_NONE,
                'Update mapping and data for asset index',
                null
            )
            ->addOption(
                self::OPTION_RECREATE_INDEX,
                'r',
                InputOption::VALUE_NONE,
                'Delete and recreate search indices',
                null
            )
            ->addOption(
                self::UPDATE_GLOBAL_ALIASES_ONLY,
                null,
                InputOption::VALUE_NONE,
                'Updates the global index aliases for data-object and element-search indices only.',
                null
            )
            ->setDescription(
                'Updates index/mapping for all classDefinitions/asset without ' .
                'deleting them. Adds there elements to index queue.'
            );

        $this->addCalculatedFieldsModeOption();
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

        if (!$this->applyCalculatedFieldsModeOption($input, $output)) {
            $this->release();

            return self::INVALID;
        }

        if ($input->getOption(self::UPDATE_GLOBAL_ALIASES_ONLY)) {
            $this->updateGlobalIndexAliases();

            return self::SUCCESS;
        }

        $this->indexUpdateService->setReCreateIndex($input->getOption(self::OPTION_RECREATE_INDEX));

        $updateAll = true;
        $failed = false;

        /** @var string|null $classDefinitionId */
        $classDefinitionId = $input->getOption(self::OPTION_CLASS_DEFINITION_ID);

        if ($classDefinitionId) {
            $updateAll = false;

            try {
                $classDefinition = ClassDefinition::getById($classDefinitionId);
                if (!$classDefinition) {
                    throw new IdNotFoundException(
                        sprintf('ClassDefinition with id %s not found', $classDefinitionId)
                    );
                }

                $this->output->writeln(
                    sprintf(
                        '<info>Update index and indices for ClassDefinition with id %s</info>',
                        $classDefinitionId
                    ),
                    OutputInterface::VERBOSITY_NORMAL
                );

                $this
                    ->indexUpdateService
                    ->updateClassDefinition($classDefinition);
            } catch (Exception $e) {
                $failed = true;
                $this->writeSectionError(sprintf('Updating ClassDefinition %s', $classDefinitionId), $e);
            }
        }

        if ($input->getOption(self::OPTION_UPDATE_ASSET_INDEX)) {
            $updateAll = false;

            try {
                $output->writeln(
                    '<info>Update asset index</info>',
                    OutputInterface::VERBOSITY_NORMAL
                );

                $this
                    ->indexUpdateService
                    ->updateAssets();
            } catch (Exception $e) {
                $failed = true;
                $this->writeSectionError('Updating asset index', $e);
            }
        }

        if ($updateAll) {
            try {
                $this->output->writeln(
                    '<info>Update all mappings and indices for objects/assets</info>',
                    OutputInterface::VERBOSITY_NORMAL
                );

                $this
                    ->indexUpdateService
                    ->updateAll();
            } catch (Exception $e) {
                $failed = true;
                $this->writeSectionError('Updating all mappings and indices', $e);
            }
        }

        $this->output->writeln(
            '<info>Dispatch queue messages</info>',
            OutputInterface::VERBOSITY_NORMAL
        );

        $this->enqueueService->dispatchQueueMessages(true);
        $this->updateGlobalIndexAliases();

        $this->release();

        if ($failed) {
            // One or more sections failed. Return a non-zero exit code so callers (deployment
            // pipelines, CI) see the failure instead of a false success. All sections are still
            // attempted first, so a single failure does not hide the others.
            $this->output->writeln(
                '<error>Finished with errors - see above. The index may be incomplete.</error>'
            );

            return self::FAILURE;
        }

        $this->output->writeln('<info>Finished</info>', OutputInterface::VERBOSITY_NORMAL);

        return self::SUCCESS;
    }

    /**
     * Writes a section failure including the exception type and its cause chain, so the actual
     * reason is visible and not reduced to a bare message.
     */
    private function writeSectionError(string $context, Exception $e): void
    {
        // Escape dynamic values before wrapping them in <error> markup: an exception message (or the
        // class id in $context) containing something like "<field>" would otherwise be parsed as a
        // console style tag and could throw from OutputFormatter - aborting the error reporting, the
        // remaining sections and the queue dispatch, which is exactly what this command must avoid.
        $this->output->writeln(sprintf(
            '<error>%s failed: %s: %s</error>',
            OutputFormatter::escape($context),
            $e::class,
            OutputFormatter::escape($e->getMessage())
        ));

        for ($previous = $e->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
            $this->output->writeln(sprintf(
                '<error>  caused by %s: %s</error>',
                $previous::class,
                OutputFormatter::escape($previous->getMessage())
            ));
        }
    }

    private function updateGlobalIndexAliases(): void
    {
        $this->output->writeln(
            '<info>Update global aliases</info>',
            OutputInterface::VERBOSITY_VERBOSE
        );
        $this->globalIndexAliasService->updateDataObjectAlias();
        $this->globalIndexAliasService->updateElementSearchAlias();
    }
}
