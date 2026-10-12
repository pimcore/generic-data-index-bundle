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

namespace Functional\Command;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * generic-data-index:deployment:reindex with index options against a real search index.
 */
class DeploymentReindexScopeTest extends Unit
{
    private const COMMAND = 'generic-data-index:deployment:reindex';

    private const CUSTOM_FIELD = 'deploymentReindexTestField';

    protected IndexTester $tester;

    /** @var callable|null */
    private $mappingListener = null;

    protected function _before(): void
    {
        $this->deleteIndexState();
        $this->tester->clearQueue();
    }

    protected function _after(): void
    {
        if ($this->mappingListener !== null) {
            $this->getEventDispatcher()->removeListener(ExtractMappingEvent::class, $this->mappingListener);
            $this->mappingListener = null;
            // Brings the asset mapping back to the shared state of the other tests.
            $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        }

        $this->deleteIndexState();
        $this->tester->clearQueue();
    }

    public function testWithoutOptionsNoIndexStateIsStored(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND);

        $this->assertSame(0, $this->countIndexState());
    }

    public function testFirstRunOnlyStoresTheBaseline(): void
    {
        $indexName = $this->tester->getIndexName('asset');

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);

        $this->assertStringContainsString('store baseline', $output);
        $this->assertSame($indexName, $this->tester->getIndexName('asset'), 'The baseline run must not reindex');
        $this->assertSame(3, $this->countIndexState(), 'Mapping, settings and data version of the asset index');

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $this->assertStringContainsString('skip', $output);
    }

    /**
     * Predefined metadata and valid languages reach the asset mapping the same way, but only
     * after a container rebuild in this test environment.
     */
    public function testChangedAssetMappingIsApplied(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $indexName = $this->tester->getIndexName('asset');

        $this->mappingListener = static function (ExtractMappingEvent $event): void {
            $event->setCustomFieldsMapping(
                $event->getCustomFieldsMapping() + [self::CUSTOM_FIELD => ['type' => 'keyword']]
            );
        };
        $this->getEventDispatcher()->addListener(ExtractMappingEvent::class, $this->mappingListener);

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true, '--dry-run' => true]);
        $this->assertStringContainsString('mapping changed', $output);
        $this->assertStringContainsString('native reindex, rebuild from database', $output);
        $this->assertSame($indexName, $this->tester->getIndexName('asset'), '--dry-run must not change the index');

        $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);

        $newIndexName = $this->tester->getIndexName('asset');
        $this->assertNotSame($indexName, $newIndexName);
        $mapping = $this->tester->getIndexMapping($newIndexName)[$newIndexName]['mappings']['properties'];
        $this->assertArrayHasKey(self::CUSTOM_FIELD, $mapping['custom_fields']['properties']);

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $this->assertStringContainsString('skip', $output);
    }

    public function testChangedIndexSettingsOnlyTriggerANativeReindex(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--documents' => true]);
        $indexName = $this->tester->getIndexName('document');
        $this->getSettingsStore()->storeIndexState('document_settings', 'outdated');

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--documents' => true]);

        $this->assertStringContainsString('index settings changed', $output);
        $this->assertStringNotContainsString('rebuild from database', $output);
        $this->assertStringNotContainsString('Dispatch queue messages', $output);
        $this->assertNotSame($indexName, $this->tester->getIndexName('document'));
        $this->assertNotSame('outdated', $this->getSettingsStore()->getIndexState('document_settings'));
    }

    public function testChangedDataVersionRebuildsWithoutReindex(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--documents' => true]);
        $indexName = $this->tester->getIndexName('document');
        $this->getSettingsStore()->storeIndexState('document_data_version', '0.0');

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--documents' => true]);

        $this->assertStringContainsString('data version changed (0.0 -> 1.0)', $output);
        $this->assertStringContainsString('Dispatch queue messages', $output);
        $this->assertSame($indexName, $this->tester->getIndexName('document'));
        $this->assertSame('1.0', $this->getSettingsStore()->getIndexState('document_data_version'));
    }

    public function testDryRunNeedsAnIndexOption(): void
    {
        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--dry-run' => true], [], Command::INVALID);

        $this->assertStringContainsString('--dry-run needs one of', $output);
    }

    public function testAllCoversEveryIndex(): void
    {
        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--all' => true, '--dry-run' => true]);

        foreach (['data object folders', 'assets', 'documents', 'class '] as $label) {
            $this->assertStringContainsString($label, $output);
        }
        $this->assertSame(0, $this->countIndexState(), '--dry-run must not store anything');
    }

    public function testOtherReindexPathsOnlyRefreshExistingState(): void
    {
        /** @var ReindexServiceInterface $reindexService */
        $reindexService = $this->tester->grabService(ReindexServiceInterface::class);

        $reindexService->reindexAssets(false);
        $this->assertSame(0, $this->countIndexState(), 'Projects that never opted in get no entries');

        $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $this->getSettingsStore()->storeIndexState('asset_settings', 'outdated');
        $reindexService->reindexAssets(false);

        $this->assertNotSame('outdated', $this->getSettingsStore()->getIndexState('asset_settings'));
        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $this->assertStringContainsString('skip', $output);
    }

    public function testClassSettingsChangeReindexesTheClassIndex(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--classes' => true]);
        $indexName = $this->tester->getIndexName('simple', true);
        $this->getSettingsStore()->storeIndexState($this->getSimpleClassKey() . '_settings', 'outdated');

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--classes' => true]);

        $this->assertStringContainsString('index settings changed', $output);
        $this->assertNotSame($indexName, $this->tester->getIndexName('simple', true));
        $this->assertNotSame('outdated', $this->getSettingsStore()->getIndexState($this->getSimpleClassKey() . '_settings'));
    }

    public function testClassDataVersionChangeRebuildsTheClass(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--classes' => true]);
        $indexName = $this->tester->getIndexName('simple', true);
        $this->getSettingsStore()->storeIndexState($this->getSimpleClassKey() . '_data_version', '0.0');

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--classes' => true]);

        $this->assertStringContainsString('data version changed (0.0 -> 1.0)', $output);
        $this->assertStringContainsString('Dispatch queue messages', $output);
        $this->assertSame($indexName, $this->tester->getIndexName('simple', true));
    }

    public function testMissingClassIndexIsCreated(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--classes' => true]);
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        $searchIndexService->deleteIndex($this->tester->getIndexName('simple', true));
        $alias = $this->tester->grabService(DataObjectTypeAdapter::class)
            ->getAliasIndexName(ClassDefinition::getByName('simple'));
        $this->assertFalse($searchIndexService->existsAlias($alias));

        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--classes' => true]);

        $this->assertStringContainsString('index missing', $output);
        $this->assertTrue($searchIndexService->existsAlias($alias));
    }

    public function testUpdateIndexRefreshesTheMappingButNotTheSettingsWithoutRecreation(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $this->getSettingsStore()->storeIndexState('asset_mapping', 'outdated');
        $this->getSettingsStore()->storeIndexState('asset_settings', 'outdated');

        /** @var IndexUpdateServiceInterface $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $indexUpdateService->setReCreateIndex(false)->updateAssets();

        $this->assertNotSame('outdated', $this->getSettingsStore()->getIndexState('asset_mapping'));
        $this->assertSame(
            'outdated',
            $this->getSettingsStore()->getIndexState('asset_settings'),
            'Index settings are only applied when the index is created'
        );
    }

    public function testNativeReindexWithoutRebuildKeepsAnUnfinishedSnapshotImportDetectable(): void
    {
        $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $this->getSettingsStore()->storeIndexState('asset_mapping', IndexStateServiceInterface::INVALIDATED);

        $this->tester->grabService(ReindexServiceInterface::class)->reindexAssets(false);

        $this->assertSame(IndexStateServiceInterface::INVALIDATED, $this->getSettingsStore()->getIndexState('asset_mapping'));
        $output = $this->tester->runConsoleCommand(self::COMMAND, ['--assets' => true]);
        $this->assertStringContainsString('previous snapshot import did not finish', $output);
        $this->assertStringContainsString('native reindex, rebuild from database', $output);
    }

    public function testConfiguredIndexVersionsAreUsedPerIndex(): void
    {
        $classId = ClassDefinition::getByName('simple')->getId();
        $indexStateService = new IndexStateService(
            $this->getSettingsStore(),
            $this->tester->grabService(SearchIndexServiceInterface::class),
            $this->tester->grabService(SearchIndexConfigServiceInterface::class),
            $this->tester->grabService(AssetIndexHandler::class),
            $this->tester->grabService(DocumentIndexHandler::class),
            $this->tester->grabService(DataObjectIndexHandler::class),
            $this->tester->grabService(AssetTypeAdapter::class),
            $this->tester->grabService(DocumentTypeAdapter::class),
            $this->tester->grabService(DataObjectTypeAdapter::class),
            ['assets' => 2, 'documents' => 0, 'folders' => 5, 'classes' => [$classId => 4]],
        );

        $version = static fn (IndexStateTarget $target) => $indexStateService->getCurrentState($target)->getDataVersion();

        $this->assertSame('1.2', $version(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS)));
        $this->assertSame('1.0', $version(IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS)));
        $this->assertSame('1.5', $version(IndexStateTarget::forScope(DeploymentReindexScope::FOLDERS)));
        $this->assertSame('1.4', $version(IndexStateTarget::forClassDefinition(ClassDefinition::getByName('simple'))));
    }

    private function getSimpleClassKey(): string
    {
        return 'class_' . ClassDefinition::getByName('simple')->getId();
    }

    private function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->tester->grabService('event_dispatcher');
    }

    private function getSettingsStore(): SettingsStoreServiceInterface
    {
        return $this->tester->grabService(SettingsStoreServiceInterface::class);
    }

    private function countIndexState(): int
    {
        return (int) Db::get()->fetchOne(
            "SELECT COUNT(*) FROM settings_store WHERE scope = 'generic_data_index' AND id LIKE 'index\\_state\\_%'"
        );
    }

    private function deleteIndexState(): void
    {
        Db::get()->executeStatement(
            "DELETE FROM settings_store WHERE scope = 'generic_data_index' AND id LIKE 'index\\_state\\_%'"
        );
    }
}
