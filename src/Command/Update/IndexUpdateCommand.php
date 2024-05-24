<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Command\Update;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\CommandAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IdNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class IndexUpdateCommand extends AbstractCommand
{
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
                'cid',
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

        if ($input->getOption(self::UPDATE_GLOBAL_ALIASES_ONLY)) {
            $this->updateGlobalIndexAliases();

            return self::SUCCESS;
        }

        $this->indexUpdateService->setReCreateIndex($input->getOption(self::OPTION_RECREATE_INDEX));

        $updateAll = true;

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
                    OutputInterface::VERBOSITY_VERBOSE
                );

                $this
                    ->indexUpdateService
                    ->updateClassDefinition($classDefinition);
            } catch (Exception $e) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            }
        }

        if ($input->getOption(self::OPTION_UPDATE_ASSET_INDEX)) {
            $updateAll = false;

            try {
                $output->writeln(
                    '<info>Update asset index</info>',
                    OutputInterface::VERBOSITY_VERBOSE
                );

                $this
                    ->indexUpdateService
                    ->updateAssets();
            } catch (Exception $e) {
                $this->output->writeln($e->getMessage());
            }
        }

        if ($updateAll) {
            try {
                $this->output->writeln(
                    '<info>Update all mappings and indices for objects/assets</info>',
                    OutputInterface::VERBOSITY_VERBOSE
                );

                $this
                    ->indexUpdateService
                    ->updateAll();
            } catch (Exception $e) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            }
        }

        $this->output->writeln(
            '<info>Dispatch queue messages</info>',
            OutputInterface::VERBOSITY_VERBOSE
        );

        $this->enqueueService->dispatchQueueMessages(true);
        $this->updateGlobalIndexAliases();

        $this->release();

        $this->output->writeln('<info>Finished</info>', OutputInterface::VERBOSITY_VERBOSE);

        return self::SUCCESS;
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
