<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Lib\ModuleContainer;
use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Installer as GenericDataIndexInstaller;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Console\Application;
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
        //create migrations table in order to allow installation - needed for SettingsStoreAware Installer
        \Pimcore\Db::get()->fetchAllAssociative('
            create table migration_versions
            (
                version varchar(1024) not null
                    primary key,
                executed_at datetime null,
                execution_time int null
            )
            collate=utf8_unicode_ci;
            ');

        if ($this->config['run_installer']) {
            /** @var Pimcore $pimcoreModule */
            $pimcoreModule = $this->getModule('\\' . Pimcore::class);

            $this->debug('[Generic Data Index] Running bundle installer');

            $genericDataIndexInstaller = $pimcoreModule->getContainer()->get(
                GenericDataIndexInstaller::class
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

    public function getIndexSearchClient(): mixed
    {
        return $this->grabService('generic-data-index.opensearch-client');
    }

    public function checkIndexEntry(string $id, string $index): array
    {

        /** @var Client $client */
        $client = $this->getIndexSearchClient();
        $response = $client->get([
            'id' => $id,
            'index' => $index,
        ]);

        $this->assertEquals($id, $response['_id'], 'Check ES document id of element');

        return $response;
    }

    public function flushIndex()
    {
        $client = $this->getIndexSearchClient();
        $client->indices()->refresh();
        $client->indices()->flush();
    }

    public function cleanupIndex()
    {
        $client = $this->getIndexSearchClient();
        $client->deleteByQuery([
            'index' => '*',
            'body' => [
                'query' => [
                    'match_all' => (object)[],
                ],
            ],
        ]);
    }
}
