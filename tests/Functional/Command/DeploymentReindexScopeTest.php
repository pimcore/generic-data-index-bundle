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
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
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
