<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Command\Update;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\CommandAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class DeploymentReindex extends AbstractCommand
{
    use LockableTrait;

    public function __construct(
        private readonly DataObjectIndexHandler $indexHandler,
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly IndexUpdateServiceInterface $indexUpdateService,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
        string $name = null
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
            $updatedIds = [];
            $classesList = new ClassDefinition\Listing();
            $classes = $classesList->load();

            foreach ($classes as $classDefinition) {
                $classDefinitionId = $classDefinition->getId();
                $currentCheckSum = $this->indexHandler->getClassMappingCheckSum(
                    $this->indexHandler->getMappingProperties($classDefinition)
                );
                $storedCheckSum = $this->settingsStoreService->getClassMappingCheckSum($classDefinitionId);

                if ($storedCheckSum !== $currentCheckSum) {
                    $updatedIds[] = $classDefinitionId;

                    $this
                        ->indexUpdateService
                        ->updateClassDefinition($classDefinition);
                }
            }

            if (!empty($updatedIds)) {
                $output->writeln(
                    sprintf(
                        '<info>Updated following ClassDefinitions: [%s]</info>',
                        implode(', ', $updatedIds)
                    ),
                    OutputInterface::VERBOSITY_VERBOSE
                );

                $output->writeln(
                    '<info>Dispatch queue messages</info>',
                    OutputInterface::VERBOSITY_VERBOSE
                );

                $this->enqueueService->dispatchQueueMessages(true);
            }

            $output->writeln('<info>Finished</info>', OutputInterface::VERBOSITY_VERBOSE);
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        } finally {
            $this->release();
        }

        return self::SUCCESS;
    }
}
