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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\IndexSegmentService;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\GroupConfigResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\KeyConfigResolverInterface;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;

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

    private const IDENTIFIER = 'rimMaterial';

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

    private function createService(?GroupConfig $group, ?KeyConfig $key): IndexSegmentService
    {
        return new IndexSegmentService(
            $this->makeEmpty(GroupConfigResolverInterface::class, ['getById' => $group]),
            $this->makeEmpty(KeyConfigResolverInterface::class, ['getById' => $key])
        );
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
