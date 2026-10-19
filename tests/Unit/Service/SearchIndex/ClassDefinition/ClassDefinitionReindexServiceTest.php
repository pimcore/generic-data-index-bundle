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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\ClassDefinition;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexRebuildObligationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexRebuildObligationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class ClassDefinitionReindexServiceTest extends Unit
{
    /**
     * Unsorted mapping properties: the checksum differs between the legacy
     * (insertion order) and the current (normalized) algorithm.
     */
    private const MAPPING_PROPERTIES = [
        'zulu' => ['type' => 'keyword'],
        'alpha' => ['type' => 'text'],
        'mike' => ['properties' => ['delta' => 'x', 'bravo' => 'y']],
    ];

    private const CURRENT_CHECK_SUM = 4242;

    /**
     * Mapping properties are built in class definition field order, so checksums
     * stored before the key normalization was introduced differ from the ones
     * computed now. Without recognising them, upgrading to the normalized checksum
     * would reindex every single class definition.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/pull/419
     */
    public function testLegacyCheckSumIsAcceptedAndReStampedWithoutReindexing(): void
    {
        $legacyCheckSum = crc32(json_encode(self::MAPPING_PROPERTIES, JSON_THROW_ON_ERROR));
        $storedCheckSums = [];

        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => Expected::never(),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => $legacyCheckSum,
            'storeClassMapping' => static function (string $id, int $data) use (&$storedCheckSums): void {
                $storedCheckSums[$id] = $data;
            },
        ]);

        $updated = $this->createService($handler, $settingsStore)
            ->reindexClassDefinition($this->createClassDefinition(), true);

        $this->assertFalse($updated, 'A class definition with a matching legacy checksum must not be reindexed');
        $this->assertSame(
            ['TEST' => self::CURRENT_CHECK_SUM],
            $storedCheckSums,
            'The legacy checksum must be re-stamped with the current one so it is only recognised once'
        );
    }

    public function testChangedMappingStillTriggersReindex(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => Expected::once(new MappingUpdateResult()),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => 999, // neither the current nor the legacy checksum
        ]);

        $updated = $this->createService($handler, $settingsStore)
            ->reindexClassDefinition($this->createClassDefinition(), true);

        $this->assertTrue($updated);
    }

    public function testUnchangedCurrentCheckSumSkipsReindex(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => Expected::never(),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'storeClassMapping' => Expected::never(),
        ]);

        $updated = $this->createService($handler, $settingsStore)
            ->reindexClassDefinition($this->createClassDefinition(), true);

        $this->assertFalse($updated);
    }

    /**
     * A class definition that was never indexed has no stored checksum at all and
     * must not be mistaken for an up-to-date legacy entry.
     */
    public function testMissingStoredCheckSumTriggersReindex(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => Expected::once(new MappingUpdateResult()),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => null,
        ]);

        $updated = $this->createService($handler, $settingsStore)
            ->reindexClassDefinition($this->createClassDefinition(), true);

        $this->assertTrue($updated);
    }

    /**
     * Without the skip flag every class definition is reindexed, so a matching
     * legacy checksum must not short-circuit it.
     */
    public function testLegacyCheckSumDoesNotSkipForcedReindex(): void
    {
        $legacyCheckSum = crc32(json_encode(self::MAPPING_PROPERTIES, JSON_THROW_ON_ERROR));

        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => Expected::once(new MappingUpdateResult()),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => $legacyCheckSum,
        ]);

        $updated = $this->createService($handler, $settingsStore)
            ->reindexClassDefinition($this->createClassDefinition(), false);

        $this->assertTrue($updated);
    }

    /** @dataProvider provideStoredCheckSums */
    public function testIsMappingChangedMatchesTheReindexDecision(?int $storedCheckSum, bool $expected): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => $storedCheckSum,
        ]);

        $this->assertSame(
            $expected,
            $this->createService($handler, $settingsStore)->isMappingChanged($this->createClassDefinition())
        );
    }

    public static function provideStoredCheckSums(): array
    {
        return [
            'no stored checksum' => [null, true],
            'current checksum' => [self::CURRENT_CHECK_SUM, false],
            'legacy checksum' => [crc32(json_encode(self::MAPPING_PROPERTIES, JSON_THROW_ON_ERROR)), false],
            'other checksum' => [1234, true],
        ];
    }

    /**
     * Fields left out of the copy are only restored by the rebuild, so the objects are queued
     * even when the caller (e.g. generic-data-index:reindex) did not ask for a rebuild.
     */
    public function testStrippedFieldsQueueTheObjectsEvenWithoutAnEnqueueRequest(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => new MappingUpdateResult(['standard_fields.price']),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, ['getClassMappingCheckSum' => 999]);

        $result = $this->createService($handler, $settingsStore, $enqueueService)
            ->reindexClassDefinitionMapping($this->createClassDefinition(), true, false);

        $this->assertSame(['standard_fields.price'], $result?->getStrippedPaths());
    }

    public function testAPlainCopyDoesNotQueueWithoutAnEnqueueRequest(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => new MappingUpdateResult(),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => Expected::never(),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, ['getClassMappingCheckSum' => 999]);

        $this->assertTrue(
            $this->createService($handler, $settingsStore, $enqueueService)
                ->reindexClassDefinition($this->createClassDefinition(), true, false)
        );
    }

    /**
     * The checksum is stored right after the copy; a failing icon update afterwards must not leave a stripped
     * copy without its rebuild, because the retry finds the checksum current and skips the class.
     */
    public function testAFailingIconUpdateDoesNotLoseTheRebuild(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => new MappingUpdateResult(['standard_fields.price']),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => 999,
            'storeClassMapping' => Expected::once(),
        ]);
        $iconService = $this->makeEmpty(IndexIconUpdateServiceInterface::class, [
            'updateIcon' => static fn () => throw new Exception('version_conflict_engine_exception'),
        ]);

        $this->expectException(ClassDefinitionIndexUpdateFailedException::class);
        $this->createService($handler, $settingsStore, $enqueueService, $iconService)
            ->reindexClassDefinitionMapping($this->createClassDefinition(), true, false);
    }

    /**
     * If queueing the rebuild fails, the checksum stays unstored, so the retry handles the class again.
     */
    public function testAFailingEnqueueLeavesTheChecksumUnstored(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingProperties' => self::MAPPING_PROPERTIES,
            'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            'reindexMappingWithResult' => new MappingUpdateResult(['standard_fields.price']),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => static fn () => throw new Exception('Connection lost'),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => 999,
            'storeClassMapping' => Expected::never(),
        ]);

        $this->expectException(ClassDefinitionIndexUpdateFailedException::class);
        $this->createService($handler, $settingsStore, $enqueueService)
            ->reindexClassDefinitionMapping($this->createClassDefinition(), true, false);
    }

    /**
     * A failing enqueue after a copy that stripped fields: the retry sees the current mapping and a copy that strips
     * nothing, but still queues the rebuild, because the first run left the obligation.
     */
    public function testARebuildOwedByAFailedEnqueueIsQueuedByTheRetry(): void
    {
        $store = [];
        $obligations = $this->createObligationService($store);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, ['getClassMappingCheckSum' => 999]);
        $firstRun = $this->createService(
            $this->makeEmpty(IndexHandlerInterface::class, [
                'getMappingProperties' => self::MAPPING_PROPERTIES,
                'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
                'reindexMappingWithResult' => new MappingUpdateResult(['standard_fields.price']),
            ]),
            $settingsStore,
            $this->makeEmpty(EnqueueServiceInterface::class, [
                'enqueueByClassDefinition' => static fn () => throw new Exception('Connection lost'),
            ]),
            obligations: $obligations
        );

        try {
            $firstRun->reindexClassDefinitionMapping($this->createClassDefinition(), false, false);
            $this->fail('Expected the enqueue failure');
        } catch (ClassDefinitionIndexUpdateFailedException) {
        }
        $this->assertTrue($obligations->isOwed(IndexStateTarget::forClassDefinition($this->createClassDefinition())));

        $retry = $this->createService(
            $this->makeEmpty(IndexHandlerInterface::class, [
                'getMappingProperties' => self::MAPPING_PROPERTIES,
                'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
                'reindexMappingWithResult' => new MappingUpdateResult(),
            ]),
            $settingsStore,
            $this->makeEmpty(EnqueueServiceInterface::class, [
                'enqueueByClassDefinition' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
            ]),
            obligations: $obligations
        );
        $result = $retry->reindexClassDefinitionMapping($this->createClassDefinition(), false, false);

        $this->assertTrue($result?->isRebuildOwed());
        $this->assertSame([], $store, 'Settled once the rebuild is queued');
    }

    /**
     * The checksum was stored before the enqueue failed (e.g. by an older version): the class is skipped as
     * unchanged, but the owed rebuild is still queued.
     */
    public function testAnOwedRebuildIsQueuedForAnUnchangedClassToo(): void
    {
        $store = [];
        $obligations = $this->createObligationService($store);
        $obligations->owe(IndexStateTarget::forClassDefinition($this->createClassDefinition()));
        $service = $this->createService(
            $this->makeEmpty(IndexHandlerInterface::class, [
                'getMappingProperties' => self::MAPPING_PROPERTIES,
                'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
                'reindexMappingWithResult' => Expected::never(),
            ]),
            $this->makeEmpty(SettingsStoreServiceInterface::class, [
                'getClassMappingCheckSum' => self::CURRENT_CHECK_SUM,
            ]),
            $this->makeEmpty(EnqueueServiceInterface::class, [
                'enqueueByClassDefinition' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
            ]),
            obligations: $obligations
        );

        $this->assertTrue($service->reindexClassDefinition($this->createClassDefinition(), true, false));
        $this->assertSame([], $store);
    }

    /**
     * @param array<string, string> $store
     */
    private function createObligationService(array &$store): IndexRebuildObligationService
    {
        return new IndexRebuildObligationService(
            $this->makeEmpty(SettingsStoreServiceInterface::class, [
                'getIndexState' => static function (string $key) use (&$store): ?string {
                    return $store[$key] ?? null;
                },
                'storeIndexState' => static function (string $key, string $value) use (&$store): void {
                    $store[$key] = $value;
                },
                'removeIndexState' => static function (string $key) use (&$store): void {
                    unset($store[$key]);
                },
            ]),
            $this->makeEmpty(TimeServiceInterface::class, ['getCurrentMillisecondTimestamp' => 1])
        );
    }

    private function createService(
        IndexHandlerInterface $handler,
        SettingsStoreServiceInterface $settingsStore,
        ?EnqueueServiceInterface $enqueueService = null,
        ?IndexIconUpdateServiceInterface $iconService = null,
        ?IndexRebuildObligationServiceInterface $obligations = null,
    ): ClassDefinitionReindexService {
        return new ClassDefinitionReindexService(
            $handler,
            $enqueueService ?? $this->makeEmpty(EnqueueServiceInterface::class),
            $settingsStore,
            $iconService ?? $this->makeEmpty(IndexIconUpdateServiceInterface::class, ['updateIcon' => null]),
            $this->makeEmpty(LoggerInterface::class),
            $obligations ?? $this->makeEmpty(IndexRebuildObligationServiceInterface::class),
        );
    }

    private function createClassDefinition(): ClassDefinition
    {
        $classDefinition = new ClassDefinition();
        $classDefinition->setId('TEST');
        $classDefinition->setName('Test');
        $classDefinition->setIcon('/bundles/pimcoreadmin/img/flat-color-icons/cube.svg');

        return $classDefinition;
    }
}
