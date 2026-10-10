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

namespace Functional\SearchIndex;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber\WorkflowPlaceIndexUpdateSubscriber;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\PqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowPlaceServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document\Page;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Tests\Support\Util\TestHelper;
use Pimcore\Workflow\Manager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Covers the gdi_test_state_table workflow from .github/ci/files/config/packages/test/config.yaml.
 * It only supports elements whose key starts with "gdi-wf-", so other tests are not affected.
 */
class WorkflowPlacesIndexingTest extends Unit
{
    private const WORKFLOW = 'gdi_test_state_table';

    private const KEY_PREFIX = 'gdi-wf-';

    /**
     * @var IndexTester
     */
    protected $tester;

    protected function _before(): void
    {
        $this->tester->clearQueue();
        $this->tester->enableSynchronousProcessing();
        $this->tester->enableSynchronousProcessingRelatedIds();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        Db::get()->executeStatement('DELETE FROM element_workflow_state WHERE workflow = ?', [self::WORKFLOW]);
        $this->tester->clearQueue();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testInitialPlaceIsIndexedWithoutElementSave(): void
    {
        // Pimcore writes the initial place into element_workflow_state after the element was added.
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $this->consumeQueue();

        $this->assertSame(['gdi_wf_draft'], $this->getIndexedPlaces($object));
    }

    public function testPlaceIsUpdatedAfterTransitionWithoutElementSave(): void
    {
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $modificationDate = $object->getModificationDate();
        $this->consumeQueue();
        $queueMessages = $this->countQueueMessages();

        $this->workflowManager()->applyWithAdditionalData(
            $this->getWorkflow($object),
            $object,
            'gdi_wf_finish_directly',
            [],
            false
        );

        $this->assertSame([[(string) $object->getId(), 'dataObject']], $this->getQueueEntries());
        $this->assertSame($queueMessages, $this->countQueueMessages());
        $this->consumeQueue();

        $this->assertSame($modificationDate, Concrete::getById($object->getId(), ['force' => true])->getModificationDate());
        $this->assertSame(['gdi_wf_done'], $this->getIndexedPlaces($object));
    }

    public function testPlaceIsUpdatedAfterGlobalAction(): void
    {
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $workflow = $this->getWorkflow($object);
        $this->workflowManager()->applyWithAdditionalData($workflow, $object, 'gdi_wf_finish_directly', [], false);
        $this->consumeQueue();
        $this->assertSame(['gdi_wf_done'], $this->getIndexedPlaces($object));

        $this->workflowManager()->applyGlobalAction($workflow, $object, 'gdi_wf_reset', [], false);
        $this->consumeQueue();

        $this->assertSame(['gdi_wf_draft'], $this->getIndexedPlaces($object));
    }

    public function testIndexMatchesStoredPlaceAfterFailedSave(): void
    {
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $this->consumeQueue();
        $queueMessages = $this->countQueueMessages();

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->tester->grabService('event_dispatcher');
        $failingSave = static function (): void {
            throw new ValidationException('save fails in test');
        };
        $eventDispatcher->addListener(DataObjectEvents::PRE_UPDATE, $failingSave);

        try {
            $this->workflowManager()->applyWithAdditionalData(
                $this->getWorkflow($object),
                $object,
                'gdi_wf_finish_directly',
                [],
                true
            );
            $this->fail('The element save was expected to fail');
        } catch (ValidationException) {
            // Pimcore versions with the rollback restore gdi_wf_draft, older ones keep gdi_wf_done.
        } finally {
            $eventDispatcher->removeListener(DataObjectEvents::PRE_UPDATE, $failingSave);
        }

        // Dispatched when the command ends, so a worker does not index the place before it was rolled back.
        $this->assertSame($queueMessages, $this->countQueueMessages());
        $this->consumeQueue();

        $storedPlace = Db::get()->fetchOne(
            'SELECT place FROM element_workflow_state WHERE cid = ? AND ctype = "object" AND workflow = ?',
            [$object->getId(), self::WORKFLOW]
        );
        $this->assertContains($storedPlace, ['gdi_wf_draft', 'gdi_wf_done']);
        $this->assertSame([$storedPlace], $this->getIndexedPlaces($object));
    }

    public function testOnlyTheElementIsEnqueued(): void
    {
        $asset = TestHelper::createImageAsset(self::KEY_PREFIX);
        // A regular asset update also enqueues the elements depending on the asset.
        $object = TestHelper::createEmptyObject('other-', false);
        $object->setHref($asset);
        $object->save();
        $this->consumeQueue();
        $this->tester->disableSynchronousProcessing();

        $this->workflowManager()->applyWithAdditionalData(
            $this->getWorkflow($asset),
            $asset,
            'gdi_wf_finish_directly',
            [],
            false
        );

        $this->assertSame([[(string) $asset->getId(), 'asset']], $this->getQueueEntries());
    }

    public function testMultiplePlacesAreIndexedAndSearchable(): void
    {
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $this->workflowManager()->applyWithAdditionalData(
            $this->getWorkflow($object),
            $object,
            'gdi_wf_start_editing',
            [],
            false
        );
        $this->consumeQueue();

        $places = $this->getIndexedPlaces($object);
        sort($places);
        $this->assertSame(['gdi_wf_edit_images', 'gdi_wf_edit_text'], $places);

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $search = $searchProvider->createDataObjectSearch()
            ->addModifier(new PqlFilter(
                'system_fields.workflowPlaces.' . self::WORKFLOW . ' = "gdi_wf_edit_text"'
            ));
        $this->assertSame([$object->getId()], $searchService->search($search)->getIds());

        $search = $searchProvider->createDataObjectSearch()
            ->addModifier(new PqlFilter('system_fields.workflowPlaces.' . self::WORKFLOW . ' = "gdi_wf_done"'));
        $this->assertSame([], $searchService->search($search)->getIds());
    }

    public function testNothingIsIndexedWithoutStateRow(): void
    {
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $this->consumeQueue();
        // Simulates an element that existed before the workflow was configured.
        Db::get()->executeStatement(
            'DELETE FROM element_workflow_state WHERE cid = ? AND ctype = "object" AND workflow = ?',
            [$object->getId(), self::WORKFLOW]
        );
        $object->save();

        $source = $this->getIndexedSource($object);
        $this->assertArrayNotHasKey('workflowPlaces', $source['system_fields']);
    }

    public function testStoredPlaceIsIndexedIndependentOfSupportStrategy(): void
    {
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        // The support strategy may depend on the user, which index workers do not have.
        $object->setKey('other-' . $object->getKey());
        $object->save();

        $this->assertNull($this->workflowManager()->getWorkflowIfExists($object, self::WORKFLOW));
        $this->assertSame(['gdi_wf_draft'], $this->getIndexedPlaces($object));
    }

    public function testElementWithoutWorkflowHasNoPlaces(): void
    {
        $object = TestHelper::createEmptyObject('other-');

        $source = $this->getIndexedSource($object);
        $this->assertArrayNotHasKey('workflowPlaces', $source['system_fields']);
    }

    public function testAssetAndDocumentPlacesAreIndexed(): void
    {
        $asset = TestHelper::createImageAsset(self::KEY_PREFIX);
        $document = TestHelper::createEmptyDocumentPage(self::KEY_PREFIX);

        $this->workflowManager()->applyWithAdditionalData(
            $this->getWorkflow($asset),
            $asset,
            'gdi_wf_finish_directly',
            [],
            false
        );
        $this->workflowManager()->applyWithAdditionalData(
            $this->getWorkflow($document),
            $document,
            'gdi_wf_finish_directly',
            [],
            false
        );
        $this->consumeQueue();

        $this->assertSame(['gdi_wf_done'], $this->getIndexedPlaces($asset));
        $this->assertSame(['gdi_wf_done'], $this->getIndexedPlaces($document));
    }

    public function testDocumentWithPlacesIsWrittenAgainWhenAppliedMappingChanges(): void
    {
        // Places can be part of a document before the index mapping knew their workflow (dynamic: false):
        // they are only searchable after the document was written again with the new mapping applied.
        /** @var SettingsStoreServiceInterface $settingsStore */
        $settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        /** @var SearchIndexConfigServiceInterface $searchIndexConfigService */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $indexAlias = $searchIndexConfigService->getIndexName($object->getClassName(), true);
        $original = $settingsStore->getWorkflowPlacesMappingChecksum($indexAlias);

        try {
            $settingsStore->storeWorkflowPlacesMappingChecksum($indexAlias, 1);
            $this->indexQueueService()->updateIndexQueue($object, 'update', true, false)->commit();
            $checksum = $this->getIndexedSource($object)['system_fields']['checksum'];

            // Same data, same mapping: the document is not written again.
            $this->indexQueueService()->updateIndexQueue($object, 'update', true, false)->commit();
            $this->assertSame($checksum, $this->getIndexedSource($object)['system_fields']['checksum']);

            // "update:index" applied a changed workflowPlaces mapping.
            $settingsStore->storeWorkflowPlacesMappingChecksum($indexAlias, 2);
            $this->indexQueueService()->updateIndexQueue($object, 'update', true, false)->commit();
            $this->assertNotSame($checksum, $this->getIndexedSource($object)['system_fields']['checksum']);
        } finally {
            if ($original !== null) {
                $settingsStore->storeWorkflowPlacesMappingChecksum($indexAlias, $original);
            } else {
                // No marker was stored before: remove the test value (not part of the service interface).
                Db::get()->executeStatement(
                    'DELETE FROM settings_store WHERE id = ? AND scope = ?',
                    ['workflow_places_mapping_' . $indexAlias, 'generic_data_index']
                );
            }
        }
    }

    public function testUpdateIndexStoresAppliedWorkflowPlacesMappingPerIndex(): void
    {
        /** @var SettingsStoreServiceInterface $settingsStore */
        $settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        /** @var SearchIndexConfigServiceInterface $searchIndexConfigService */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        /** @var WorkflowPlaceServiceInterface $workflowPlaceService */
        $workflowPlaceService = $this->tester->grabService(WorkflowPlaceServiceInterface::class);
        $assetIndex = $searchIndexConfigService->getIndexName(IndexName::ASSET->value);
        $documentIndex = $searchIndexConfigService->getIndexName(IndexName::DOCUMENT->value);
        $originalAsset = $settingsStore->getWorkflowPlacesMappingChecksum($assetIndex);
        $originalDocument = $settingsStore->getWorkflowPlacesMappingChecksum($documentIndex);
        $settingsStore->storeWorkflowPlacesMappingChecksum($assetIndex, 1);
        $settingsStore->storeWorkflowPlacesMappingChecksum($documentIndex, 1);

        /** @var IndexUpdateServiceInterface $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $indexUpdateService->setReCreateIndex(false);

        try {
            $indexUpdateService->updateAssets();

            $expected = crc32(json_encode($workflowPlaceService->getMapping(), JSON_THROW_ON_ERROR));
            $this->assertSame($expected, $settingsStore->getWorkflowPlacesMappingChecksum($assetIndex));
            // Only the updated index is touched.
            $this->assertSame(1, $settingsStore->getWorkflowPlacesMappingChecksum($documentIndex));
        } finally {
            foreach ([$assetIndex => $originalAsset, $documentIndex => $originalDocument] as $index => $original) {
                if ($original !== null) {
                    $settingsStore->storeWorkflowPlacesMappingChecksum($index, $original);
                } else {
                    Db::get()->executeStatement(
                        'DELETE FROM settings_store WHERE id = ? AND scope = ?',
                        ['workflow_places_mapping_' . $index, 'generic_data_index']
                    );
                }
            }
        }
    }

    public function testWorkflowPlacesAreMappedAsKeyword(): void
    {
        foreach ([IndexName::ASSET->value, IndexName::DOCUMENT->value] as $name) {
            $this->assertWorkflowPlacesMapping($this->tester->getIndexName($name));
        }

        $object = TestHelper::createEmptyObject(self::KEY_PREFIX);
        $this->assertWorkflowPlacesMapping($this->tester->getIndexName($object->getClassName(), true));
    }

    private function assertWorkflowPlacesMapping(string $indexName): void
    {
        $mapping = $this->tester->getIndexMapping($indexName);
        $field = $mapping[$indexName]['mappings']['properties']['system_fields']['properties']['workflowPlaces'];

        $this->assertSame('keyword', $field['properties'][self::WORKFLOW]['type'], $indexName);
    }

    private function indexQueueService(): IndexQueueServiceInterface
    {
        return $this->tester->grabService(IndexQueueServiceInterface::class);
    }

    private function workflowManager(): Manager
    {
        return $this->tester->grabService(Manager::class);
    }

    private function getWorkflow(ElementInterface $element): WorkflowInterface
    {
        $workflow = $this->workflowManager()->getWorkflowIfExists($element, self::WORKFLOW);
        $this->assertNotNull($workflow);

        return $workflow;
    }

    /**
     * @throws Exception
     */
    private function consumeQueue(): void
    {
        // Like the end of a request or command, which dispatches the queue messages of workflow place changes.
        /** @var WorkflowPlaceIndexUpdateSubscriber $subscriber */
        $subscriber = $this->tester->grabService(WorkflowPlaceIndexUpdateSubscriber::class);
        $subscriber->dispatchQueueMessages();

        $this->tester->consume();
        $this->tester->flushIndex();
    }

    private function countQueueMessages(): int
    {
        return (int) Db::get()->fetchOne(
            'SELECT count(*) FROM messenger_messages WHERE queue_name = "pimcore_generic_data_index_queue"'
        );
    }

    private function getQueueEntries(): array
    {
        return array_map(
            static fn (array $row) => [(string) $row['elementId'], $row['elementType']],
            Db::get()->fetchAllAssociative('SELECT elementId, elementType FROM generic_data_index_queue')
        );
    }

    private function getIndexedPlaces(ElementInterface $element): array
    {
        $source = $this->getIndexedSource($element);

        return $source['system_fields']['workflowPlaces'][self::WORKFLOW] ?? [];
    }

    private function getIndexedSource(ElementInterface $element): array
    {
        /** @var SearchIndexConfigServiceInterface $searchIndexConfigService */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);

        $indexName = match (true) {
            $element instanceof Concrete => $searchIndexConfigService->getIndexName($element->getClassName(), true),
            $element instanceof Asset => $searchIndexConfigService->getIndexName(IndexName::ASSET->value),
            $element instanceof Page => $searchIndexConfigService->getIndexName(IndexName::DOCUMENT->value),
        };

        return $this->tester->checkIndexEntry($element->getId(), $indexName)['_source'];
    }
}
