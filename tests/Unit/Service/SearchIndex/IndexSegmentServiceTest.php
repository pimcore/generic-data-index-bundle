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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\ClassificationStoreEntry;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\EngineCapabilityServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\IndexSegmentService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\StoreConfigReaderInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\GroupConfigResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\KeyConfigResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;

/**
 * The segments asserted here are the ones
 * {@see \Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter}
 * writes, which is what makes a filter built on them find anything.
 *
 * @internal
 */
final class IndexSegmentServiceTest extends Unit
{
    private const GROUP_ID = 7;

    private const KEY_ID = 42;

    private const OTHER_KEY_ID = 43;

    private const IDENTIFIER = 'rimMaterial';

    /** @var array<int, KeyConfig> */
    private array $keyConfigs = [];

    /** @var array<int, Data> */
    private array $definitions = [];

    /** @var KeyGroupRelation[] */
    private array $relations = [];

    public function testAnIdentifierIsTheSegment(): void
    {
        $service = $this->createService(
            $this->createGroup(self::IDENTIFIER, 'Rim material'),
            $this->createKey(self::IDENTIFIER, 'Rim material')
        );

        $this->assertSame(self::IDENTIFIER, $service->getGroupSegment(self::GROUP_ID));
        $this->assertSame(self::IDENTIFIER, $service->getKeySegment(self::KEY_ID));
    }

    public function testAnEntityWithoutAnIdentifierKeepsItsName(): void
    {
        $service = $this->createService($this->createGroup(null, 'Rims'), $this->createKey(null, 'Rims'));

        $this->assertSame('Rims', $service->getGroupSegment(self::GROUP_ID));
        $this->assertSame('Rims', $service->getKeySegment(self::KEY_ID));
    }

    public function testAnEmptyPathComponentIsDroppedFromAFallbackName(): void
    {
        $service = $this->createService($this->createGroup(null, '.A..B.'), $this->createKey(null, '.A..B.'));

        $this->assertSame('A.B', $service->getGroupSegment(self::GROUP_ID));
        $this->assertSame('A.B', $service->getKeySegment(self::KEY_ID));
    }

    public function testANameOfNothingButDotsHasNoSegment(): void
    {
        $service = $this->createService($this->createGroup(null, '..'), $this->createKey(null, '..'));

        $this->assertNull($service->getGroupSegment(self::GROUP_ID));
        $this->assertNull($service->getKeySegment(self::KEY_ID));
    }

    public function testAnEntityThisInstallationDoesNotHaveHasNoSegment(): void
    {
        $service = $this->createService(null, null);

        $this->assertNull($service->getGroupSegment(self::GROUP_ID));
        $this->assertNull($service->getKeySegment(self::KEY_ID));
    }

    public function testAVariantAddressesTheSiblingOfTheKey(): void
    {
        $service = $this->createService(null, $this->createKey(self::IDENTIFIER, 'Rim material'));

        $this->assertSame(self::IDENTIFIER . '_Metric', $service->getKeySegment(self::KEY_ID, 'Metric'));
    }

    public function testAVariantNameIsNormalizedLikeAnyOtherSegment(): void
    {
        $service = $this->createService(null, $this->createKey(self::IDENTIFIER, 'Rim material'));

        $this->assertSame(self::IDENTIFIER . '_A.B', $service->getKeySegment(self::KEY_ID, '.A..B'));
        $this->assertNull($service->getKeySegment(self::KEY_ID, '..'));
    }

    public function testADotFreeNameIsReturnedUnchanged(): void
    {
        $service = $this->createService(null, null);

        $this->assertSame('Rims', $service->normalizeSegment('Rims'));
    }

    /**
     * An empty name is the all-dot case with the dots left out: nothing is left to address, so it
     * has no segment either. An empty string would otherwise name a key_ sibling the index never
     * writes, and a group node the mapping never declares.
     */
    public function testAnEmptyNameHasNoSegment(): void
    {
        $service = $this->createService($this->createGroup(null, ''), $this->createKey(null, ''));

        $this->assertNull($service->normalizeSegment(''));
        $this->assertNull($service->getGroupSegment(self::GROUP_ID));
        $this->assertNull($service->getKeySegment(self::KEY_ID));
    }

    public function testAnEmptyVariantNameAddressesNoSibling(): void
    {
        $service = $this->createService(null, $this->createKey(self::IDENTIFIER, 'Rim material'));

        $this->assertNull($service->getKeySegment(self::KEY_ID, ''));
    }

    /**
     * A key the document writes one entry for: the whole answer is that entry, under the segment the
     * key is addressed by, carrying the key id and the name a caller renders instead of the segment.
     */
    public function testAPlainKeyOwnsOneEntryUnderItsSegment(): void
    {
        $this->registerKey(self::KEY_ID, 'Rim material', self::IDENTIFIER, new Input());

        $this->assertEquals(
            [self::IDENTIFIER => new ClassificationStoreEntry(self::KEY_ID, null, 'Rim material')],
            $this->createEntriesService()->getGroupEntries(self::GROUP_ID)
        );
    }

    /** The name fallback reaches the entry map too, because it is the same resolution underneath. */
    public function testAKeyWithoutAnIdentifierIsFoundUnderItsName(): void
    {
        $this->registerKey(self::KEY_ID, 'Rim material', null, new Input());

        $this->assertEquals(
            ['Rim material' => new ClassificationStoreEntry(self::KEY_ID, null, 'Rim material')],
            $this->createEntriesService()->getGroupEntries(self::GROUP_ID)
        );
    }

    /**
     * The case a caller cannot work out for itself: a multiValue key is several document entries, and
     * one that re-derives the rule by key name reads every variant value as absent.
     */
    public function testAMultiValueKeyOwnsASiblingPerDeclaredVariant(): void
    {
        $this->registerKey(
            self::KEY_ID,
            'Rim diameter',
            self::IDENTIFIER,
            $this->createMultiValue(['Metric', 'Imperial'])
        );

        $this->assertEquals(
            [
                self::IDENTIFIER => new ClassificationStoreEntry(self::KEY_ID, null, 'Rim diameter'),
                self::IDENTIFIER . '_Metric' => new ClassificationStoreEntry(self::KEY_ID, 'Metric', 'Rim diameter'),
                self::IDENTIFIER . '_Imperial' => new ClassificationStoreEntry(
                    self::KEY_ID,
                    'Imperial',
                    'Rim diameter'
                ),
            ],
            $this->createEntriesService()->getGroupEntries(self::GROUP_ID)
        );
    }

    /**
     * A multiValue key that declines the unqualified value holds nothing in the bare slot, so the
     * document never writes that entry and the map must not offer it.
     */
    public function testAMultiValueKeyDecliningTheUnqualifiedValueOwnsNoBareEntry(): void
    {
        $this->registerKey(
            self::KEY_ID,
            'Rim diameter',
            self::IDENTIFIER,
            $this->createMultiValue(['Metric'], false)
        );

        $this->assertEquals(
            [self::IDENTIFIER . '_Metric' => new ClassificationStoreEntry(self::KEY_ID, 'Metric', 'Rim diameter')],
            $this->createEntriesService()->getGroupEntries(self::GROUP_ID)
        );
    }

    /**
     * A key the index does not write is not an entry a caller can read, so it is absent rather than
     * present-and-empty. The second key is indexed and is what keeps this from passing on an empty
     * fixture.
     */
    public function testAKeyExcludedFromTheIndexOwnsNoEntry(): void
    {
        $this->registerKey(self::KEY_ID, 'Rim material', self::IDENTIFIER, new Input(), false);
        $this->registerKey(self::OTHER_KEY_ID, 'Rim width', 'rimWidth', new Input());

        $this->assertEquals(
            ['rimWidth' => new ClassificationStoreEntry(self::OTHER_KEY_ID, null, 'Rim width')],
            $this->createEntriesService()->getGroupEntries(self::GROUP_ID)
        );
    }

    /**
     * A group this installation does not have holds no document entry, which is the same answer as a
     * group whose keys are all excluded: neither is addressable, and the key listing is not even read.
     */
    public function testAGroupThisInstallationDoesNotHaveHasNoEntries(): void
    {
        $this->registerKey(self::KEY_ID, 'Rim material', self::IDENTIFIER, new Input());

        $this->assertSame([], $this->createEntriesService(false)->getGroupEntries(self::GROUP_ID));
    }

    private function createService(?GroupConfig $group, ?KeyConfig $key): IndexSegmentService
    {
        return new IndexSegmentService(
            $this->makeEmpty(GroupConfigResolverInterface::class, ['getById' => $group]),
            $this->makeEmpty(KeyConfigResolverInterface::class, ['getById' => $key]),
            $this->makeEmpty(StoreConfigReaderInterface::class),
            $this->makeEmpty(ServiceResolverInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class),
            $this->makeEmpty(EngineCapabilityServiceInterface::class)
        );
    }

    /**
     * The service over the fixture {@see self::registerKey()} built: the key configurations and the
     * relation listing of one group, every value type typed by the index and an engine that holds
     * what the keys declare, so the only thing a test varies is the key itself.
     */
    private function createEntriesService(bool $groupExists = true): IndexSegmentService
    {
        $group = (new GroupConfig())->setId(self::GROUP_ID)->setName('Rims');

        return new IndexSegmentService(
            $this->makeEmpty(GroupConfigResolverInterface::class, [
                'getById' => $groupExists ? $group : null,
            ]),
            $this->makeEmpty(KeyConfigResolverInterface::class, [
                'getById' => fn (int $id): ?KeyConfig => $this->keyConfigs[$id] ?? null,
            ]),
            $this->makeEmpty(StoreConfigReaderInterface::class, [
                'getKeyRelations' => fn (): array => $this->relations,
            ]),
            $this->makeEmpty(ServiceResolverInterface::class, [
                'getFieldDefinitionFromKeyConfig' => fn (
                    KeyConfig|KeyGroupRelation $key
                ): ?Data => $this->definitions[$key->getKeyId()] ?? null,
            ]),
            $this->makeEmpty(FieldDefinitionServiceInterface::class, [
                'getFieldDefinitionAdapter' => $this->makeEmpty(AdapterInterface::class),
            ]),
            $this->makeEmpty(EngineCapabilityServiceInterface::class, ['supports' => true])
        );
    }

    /**
     * One key of the group, in the three places the service reads it from: its configuration, the
     * relation listing of the group, and the definition the key resolves to.
     */
    private function registerKey(
        int $keyId,
        string $name,
        ?string $identifier,
        Data $definition,
        bool $indexed = true
    ): void {
        $this->keyConfigs[$keyId] = $this->createKey($identifier, $name);
        $this->definitions[$keyId] = $definition;

        $relation = new KeyGroupRelation();
        $relation->setKeyId($keyId);
        $relation->setGroupId(self::GROUP_ID);
        $relation->setName($name);
        $relation->setDefinition((string) json_encode(['fieldtype' => $definition->getFieldType()] +
            ($indexed ? [] : ['indexed' => false])));

        $this->relations[] = $relation;
    }

    /**
     * @param string[] $variantNames
     */
    private function createMultiValue(array $variantNames, bool $unqualifiedValue = true): MultiValue
    {
        $definition = new MultiValue();
        $definition->setVariants(array_map(static fn (string $name): array => ['name' => $name], $variantNames));
        $definition->setUnqualifiedValue($unqualifiedValue);
        $definition->setDelegate(new Input());

        return $definition;
    }

    private function createGroup(?string $identifier, string $name): GroupConfig
    {
        $group = new GroupConfig();
        $group->setName($name);
        if ($identifier !== null) {
            $group->setIdentifier($identifier);
        }

        return $group;
    }

    private function createKey(?string $identifier, string $name): KeyConfig
    {
        $key = new KeyConfig();
        $key->setName($name);
        if ($identifier !== null) {
            $key->setIdentifier($identifier);
        }

        return $key;
    }
}
