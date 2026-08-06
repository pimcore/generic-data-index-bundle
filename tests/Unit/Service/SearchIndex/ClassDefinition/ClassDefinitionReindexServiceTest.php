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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;

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
            'reindexMapping' => Expected::never(),
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
            'reindexMapping' => Expected::once(),
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
            'reindexMapping' => Expected::never(),
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
            'reindexMapping' => Expected::once(),
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
            'reindexMapping' => Expected::once(),
        ]);
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getClassMappingCheckSum' => $legacyCheckSum,
        ]);

        $updated = $this->createService($handler, $settingsStore)
            ->reindexClassDefinition($this->createClassDefinition(), false);

        $this->assertTrue($updated);
    }

    private function createService(
        IndexHandlerInterface $handler,
        SettingsStoreServiceInterface $settingsStore
    ): ClassDefinitionReindexService {
        return new ClassDefinitionReindexService(
            $handler,
            $this->makeEmpty(EnqueueServiceInterface::class),
            $settingsStore,
            $this->makeEmpty(IndexIconUpdateServiceInterface::class, ['updateIcon' => null]),
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
