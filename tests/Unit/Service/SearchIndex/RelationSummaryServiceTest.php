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

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\RelationSummaryService;
use Pimcore\Model\DataObject\ClassDefinition\Data\LightweightRelationLoadingInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\GdiRelationSummaryProbe;
use Pimcore\Model\DataObject\GdiRelationSummaryProbeWithHook;
use Pimcore\Model\DataObject\GdiRelationSummaryProbeWithOverriddenGetter;

require_once __DIR__ . '/Fixture/GdiRelationSummaryProbe.php';

/**
 * @internal
 */
final class RelationSummaryServiceTest extends Unit
{
    protected function _before(): void
    {
        if (!interface_exists(LightweightRelationLoadingInterface::class)) {
            $this->markTestSkipped('LightweightRelationLoadingInterface requires pimcore/pimcore 2026.3.3 or later');
        }
    }

    public function testReadsTheRelationsOfTheObjectWithoutTheGetter(): void
    {
        $object = $this->createProbe(10);
        $fieldDefinition = $this->createFieldDefinition([10 => [$this->summary(1), $this->summary(2)]]);

        $summaries = (new RelationSummaryService())->getRelationSummaries($object, $fieldDefinition, 'related', true);

        $this->assertNotNull($summaries);
        $this->assertSame(10, $summaries->getOwnerId());
        $this->assertSame([$this->summary(1), $this->summary(2)], $summaries->getSummaries());
        $this->assertSame(0, $object->getterCalls);
    }

    public function testEmptyRelationsAreInheritedFromTheNearestAncestorWithRelations(): void
    {
        $grandParent = $this->createProbe(1);
        $parent = $this->createProbe(2, $grandParent);
        $object = $this->createProbe(3, $parent);
        $fieldDefinition = $this->createFieldDefinition([1 => [$this->summary(7)], 2 => [], 3 => []]);

        $summaries = (new RelationSummaryService())->getRelationSummaries($object, $fieldDefinition, 'related', true);

        $this->assertNotNull($summaries);
        $this->assertSame(1, $summaries->getOwnerId());
        $this->assertSame([$this->summary(7)], $summaries->getSummaries());
    }

    public function testAnEmptyChainEndsAtTheTopmostAncestor(): void
    {
        $parent = $this->createProbe(2);
        $object = $this->createProbe(3, $parent);
        $fieldDefinition = $this->createFieldDefinition([2 => [], 3 => []]);

        $summaries = (new RelationSummaryService())->getRelationSummaries($object, $fieldDefinition, 'related', true);

        $this->assertNotNull($summaries);
        $this->assertSame(2, $summaries->getOwnerId());
        $this->assertSame([], $summaries->getSummaries());
    }

    public function testParentsAreNotVisitedWithoutInheritance(): void
    {
        $parent = $this->createProbe(2);
        $object = $this->createProbe(3, $parent);
        $fieldDefinition = $this->createFieldDefinition([2 => [$this->summary(7)], 3 => []]);

        $summaries = (new RelationSummaryService())->getRelationSummaries($object, $fieldDefinition, 'related', false);

        $this->assertNotNull($summaries);
        $this->assertSame(3, $summaries->getOwnerId());
        $this->assertSame([], $summaries->getSummaries());
    }

    public function testFieldTypesWithoutLightweightLoadingAreReadThroughTheGetter(): void
    {
        $this->assertNull(
            (new RelationSummaryService())->getRelationSummaries(
                $this->createProbe(10),
                new ManyToOneRelation(),
                'related',
                true
            )
        );
    }

    public function testAValueAlreadyInMemoryIsReadThroughTheGetter(): void
    {
        $object = $this->createProbe(10);
        $object->markLazyKeyAsLoaded('related');

        $this->assertNull(
            (new RelationSummaryService())->getRelationSummaries(
                $object,
                $this->makeEmpty(ManyToManyObjectRelation::class, ['loadRelationSummaries' => Expected::never()]),
                'related',
                true
            )
        );
    }

    public function testAnAncestorValueAlreadyInMemoryIsReadThroughTheGetter(): void
    {
        $parent = $this->createProbe(2);
        $parent->markLazyKeyAsLoaded('related');
        $object = $this->createProbe(3, $parent);

        $this->assertNull(
            (new RelationSummaryService())->getRelationSummaries(
                $object,
                $this->createFieldDefinition([3 => []]),
                'related',
                true
            )
        );
    }

    public function testAnOverriddenGetterIsUsed(): void
    {
        $object = new GdiRelationSummaryProbeWithOverriddenGetter();
        $object->setId(10);
        $object->setClassName('GdiRelationSummaryProbe');

        $this->assertNull(
            (new RelationSummaryService())->getRelationSummaries(
                $object,
                $this->makeEmpty(ManyToManyObjectRelation::class, ['loadRelationSummaries' => Expected::never()]),
                'related',
                true
            )
        );
    }

    public function testAPreGetValueHookIsUsed(): void
    {
        $object = new GdiRelationSummaryProbeWithHook();
        $object->setId(10);
        $object->setClassName('GdiRelationSummaryProbeWithHook');

        $this->assertNull(
            (new RelationSummaryService())->getRelationSummaries(
                $object,
                $this->makeEmpty(ManyToManyObjectRelation::class, ['loadRelationSummaries' => Expected::never()]),
                'related',
                true
            )
        );
    }

    public function testAnObjectWithoutGeneratedGetterIsReadThroughTheGetter(): void
    {
        $object = $this->createProbe(10);

        $this->assertNull(
            (new RelationSummaryService())->getRelationSummaries(
                $object,
                $this->makeEmpty(ManyToManyObjectRelation::class, ['loadRelationSummaries' => Expected::never()]),
                'unknownField',
                true
            )
        );
    }

    private function createProbe(int $id, ?Concrete $inheritanceParent = null): GdiRelationSummaryProbe
    {
        $object = new GdiRelationSummaryProbe();
        $object->setId($id);
        $object->setClassName('GdiRelationSummaryProbe');
        $object->inheritanceParent = $inheritanceParent;

        return $object;
    }

    /**
     * @param array<int, list<array<string, mixed>>> $summariesByObjectId
     */
    private function createFieldDefinition(array $summariesByObjectId): ManyToManyObjectRelation
    {
        return $this->makeEmpty(ManyToManyObjectRelation::class, [
            'loadRelationSummaries' => static fn (Concrete $object) => $summariesByObjectId[$object->getId()],
        ]);
    }

    private function summary(int $id): array
    {
        return [
            'id' => $id,
            'parentId' => 1,
            'type' => 'object',
            'subtype' => 'object',
            'className' => 'GdiRelationSummaryProbe',
            'fullpath' => '/probe-' . $id,
            'filename' => 'probe-' . $id,
            'published' => false,
        ];
    }
}
