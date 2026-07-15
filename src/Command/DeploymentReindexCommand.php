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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class DeploymentReindexCommand extends AbstractCommand
{
    use LockableTrait;

    public function __construct(
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
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
                $updated = $this->classDefinitionReindexService
                    ->reindexClassDefinition($classDefinition, true, true)
                ;

                if ($updated) {
                    $updatedIds[] = $classDefinition->getId();
                }
            }

            if (!empty($updatedIds)) {
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
            } else {
                $output->writeln('<info>No updates needed - everything is up to date</info>', OutputInterface::VERBOSITY_NORMAL);
            }

            $output->writeln('<info>Finished</info>', OutputInterface::VERBOSITY_NORMAL);
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        } finally {
            $this->release();
        }

        return self::SUCCESS;
    }
}
