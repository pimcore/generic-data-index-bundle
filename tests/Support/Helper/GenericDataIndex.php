<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Lib\ModuleContainer;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Sort\TreeSortHandlers;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingRelatedIdsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Console\Application;
use Pimcore\Db;
use Pimcore\SearchClient\SearchClientInterface;
use Pimcore\Tests\Support\Helper\Pimcore;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GenericDataIndex extends \Codeception\Module
{
    public function __construct(ModuleContainer $moduleContainer, $config = null)
    {
        $this->config = array_merge($this->config, [
            'run_installer' => true,
        ]);

        parent::__construct($moduleContainer, $config);
    }

    public function runCommand(string $command, array $parameters = [], array $consoleInputs = [], int $expectedExitCode = 0): string
    {
        /** @var Pimcore $pimcoreModule */
        $pimcoreModule = $this->getModule('\\' . Pimcore::class);
        $kernel = $pimcoreModule->getKernel();
        $application = new Application($kernel);
        $consoleCommand = $application->find($command);
        $commandTester = new CommandTester($consoleCommand);
        $commandTester->setInputs($consoleInputs);

        $parameters = ['command' => $command] + $parameters;
        $exitCode = $commandTester->execute($parameters);
        $output = $commandTester->getDisplay();

        $this->assertSame(
            $expectedExitCode,
            $exitCode,
            'Command did not exit with code ' . $expectedExitCode
            . ' but with ' . $exitCode . ': ' . $output
        );

        return $output;
    }

    public function _beforeSuite($settings = []): void
    {
        if ($this->config['run_installer']) {
            /** @var Pimcore $pimcoreModule */
            $pimcoreModule = $this->getModule('\\' . Pimcore::class);

            $this->debug('[Generic Data Index] Running bundle installer');

            $genericDataIndexInstaller = $pimcoreModule->getContainer()->get(
                Installer::class
            );
            $genericDataIndexInstaller->install();

            // install generic data index
            $installer = $pimcoreModule->getContainer()->get(Installer::class);
            $installer->install();

            $this->grabService(IndexUpdateServiceInterface::class)
                ->setReCreateIndex(true)
                ->updateAll();
        }
    }

    /**
     * @var null|ContainerInterface
     */
    protected static $container = null;

    public function grabService(string $serviceId)
    {
        $pimcoreHelper = $this->getModule('\\' . Pimcore::class);

        return $pimcoreHelper->grabService($serviceId);
    }

    public function enableSynchronousProcessing(): void
    {
        $synchronousProcessing = $this->grabService(SynchronousProcessingServiceInterface::class);
        $synchronousProcessing->enable();
    }

    public function disableSynchronousProcessing(): void
    {
        $synchronousProcessing = $this->grabService(SynchronousProcessingServiceInterface::class);
        $synchronousProcessing->disable();
    }

    public function enableSynchronousProcessingRelatedIds(): void
    {
        $synchronousProcessing = $this->grabService(SynchronousProcessingRelatedIdsServiceInterface::class);
        $synchronousProcessing->enable();
    }

    public function disableSynchronousProcessingRelatedIds(): void
    {
        $synchronousProcessing = $this->grabService(SynchronousProcessingRelatedIdsServiceInterface::class);
        $synchronousProcessing->disable();
    }

    public function getIndexSearchClient(): mixed
    {
        return $this->grabService('generic-data-index.search-client');
    }

    public function checkIndexEntry(int|string $id, string $index): array
    {
        /** @var SearchClientInterface $client */
        $client = $this->getIndexSearchClient();
        $response = $client->get([
            'id' => $id,
            'index' => $index,
        ]);

        $this->assertEquals($id, $response['_id'], 'Check indexed document id of element');

        return $response;
    }

    public function checkDeletedIndexEntry(int|string $id, string $index): void
    {
        /** @var SearchClientInterface $client */
        $client = $this->getIndexSearchClient();
        $response = $client->get([
            'id' => $id,
            'index' => $index,
            'client' => ['ignore' => [404]],
        ]);

        if (isset($response['found'])) {
            $this->assertFalse($response['found'], 'Check OpenSearch document id of element');

            return;
        }

        $this->assertNotContains($id, $response);
    }

    public function flushIndex()
    {
        /** @var SearchClientInterface $client */
        $client = $this->getIndexSearchClient();
        $client->refreshIndex();
        $client->flushIndex();
    }

    public function cleanupIndex()
    {
        /** @var SearchClientInterface $client */
        $client = $this->getIndexSearchClient();

        /** @var SearchIndexConfigServiceInterface $configService */
        $configService = $this->grabService(SearchIndexConfigServiceInterface::class);
        $indexPrefix = $configService->getIndexPrefix();

        $response = $client->deleteByQuery([
            'index' => $indexPrefix . '*',
            'conflicts' => 'proceed',
            'refresh' => true,
            'body' => [
                'query' => [
                    'match_all' => (object)[],
                ],
            ],
        ]);

        $this->assertEmpty(
            $response['failures'] ?? [],
            'Cleaning up the search indices failed - leftover documents would leak into subsequent tests'
        );
    }

    public function setIndexResultWindow(
        string $indexName,
        int $windowSize = 10000): void
    {
        /** @var SearchClientInterface $client */
        $client = $this->getIndexSearchClient();

        $client->putIndexSettings([
            'index' => $indexName,
            'body' => [
                'max_result_window' => $windowSize,
            ],
        ]);
    }

    public function resetIndexWindowSettings(
        string $indexType
    ): void {
        $searchIndexConfigService = $this->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName($indexType);
        $this->setIndexResultWindow($indexName);

        $treeSort = $this->grabService(TreeSortHandlers::class);
        $treeSort->setItemsLimit(1000);
    }

    public function clearQueue()
    {
        /**
         * @var QueueMessagesDispatcher $queueMessagesDispatcher
         */
        $queueMessagesDispatcher = $this->grabService(QueueMessagesDispatcher::class);
        $queueMessagesDispatcher->clearPendingState();

        Db::get()->executeStatement(
            'delete from messenger_messages where queue_name = "pimcore_generic_data_index_queue"'
        );
        Db::get()->executeStatement(
            'truncate table generic_data_index_queue'
        );
    }

    public function getIndexName(string $name, bool $isClass = false): string
    {
        $searchIndexConfigService = $this->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName($name, $isClass);
        /** @var SearchClientInterface $client */
        $client = $this->getIndexSearchClient();
        $alias = $client->getIndexAlias([
            'name' => $indexName,
        ]);

        return array_keys($alias)[0];
    }

    public function getIndexMapping(string $indexName): array
    {
        /** @var SearchClientInterface $client */
        $client = $this->getIndexSearchClient();

        return $client->getIndexMapping(['index' => $indexName]);
    }

    public function consume(): void
    {
        $this->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
    }
}
