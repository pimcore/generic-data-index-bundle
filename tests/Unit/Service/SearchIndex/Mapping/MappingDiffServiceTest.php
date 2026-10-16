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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Mapping;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingFieldChangeAction;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;

/**
 * @internal
 */
final class MappingDiffServiceTest extends Unit
{
    private const TEXT_KEYWORD = [
        'type' => 'text',
        'fields' => [
            'keyword' => ['type' => 'keyword', 'ignore_above' => 1024],
            'sort' => ['type' => 'keyword', 'ignore_above' => 8191],
        ],
    ];

    private MappingDiffService $service;

    protected function _before(): void
    {
        $this->service = new MappingDiffService();
    }

    public function testUnchangedMappingHasNoChanges(): void
    {
        $mapping = $this->standardFields(['title' => self::TEXT_KEYWORD, 'price' => ['type' => 'float']]);

        $diff = $this->service->diff($mapping, $mapping);

        $this->assertTrue($diff->isEmpty());
    }

    /**
     * Some field adapters put the enum case itself into the mapping (e.g. video, advanced
     * many-to-many relations); the engine receives its JSON value, so the diff must compare that.
     */
    public function testEnumTypesInTheNewMappingAreComparedByTheirValue(): void
    {
        $live = $this->standardFields(['video' => ['properties' => [
            'id' => ['type' => 'text'],
            'details' => ['type' => 'nested', 'properties' => ['title' => ['type' => 'keyword']]],
        ]]]);
        $new = $this->standardFields(['video' => ['properties' => [
            'id' => ['type' => AttributeType::TEXT],
            'details' => [
                'type' => AttributeType::NESTED,
                'properties' => ['title' => ['type' => AttributeType::KEYWORD]],
            ],
        ]]]);

        $diff = $this->service->diff($live, $new);

        $this->assertTrue($diff->isEmpty(), 'An unchanged mapping built with enum cases has no changes');
    }

    public function testEnumTypeChangeIsDetected(): void
    {
        $live = $this->standardFields(['asset' => ['type' => 'text']]);
        $new = $this->standardFields(['asset' => ['type' => AttributeType::LONG]]);

        $diff = $this->service->diff($live, $new);

        $this->assertSame(['standard_fields.asset'], $diff->getStripPaths());
        $this->assertSame('long', $diff->getChanges()[0]->getNewShape());
    }

    /**
     * Date, datetime and time fields all map to the engine type date and differ only in the format.
     * Old values that the new format cannot parse fail the copy, so such a change is stripped.
     *
     * @dataProvider dateFormatProvider
     */
    public function testDateFormatChanges(?string $oldFormat, ?string $newFormat, ?string $expectedAction): void
    {
        $live = $this->standardFields(['when' => array_filter(['type' => 'date', 'format' => $oldFormat])]);
        $new = $this->standardFields(['when' => array_filter(['type' => 'date', 'format' => $newFormat])]);

        $diff = $this->service->diff($live, $new);

        if ($expectedAction === null) {
            $this->assertTrue($diff->isEmpty(), 'Every old format is accepted by the new one');

            return;
        }
        $this->assertSame(['standard_fields.when'], $diff->getStripPaths());
        $change = $diff->getChanges()[0];
        $this->assertSame(MappingFieldChangeAction::from($expectedAction), $change->getAction());
        $this->assertStringContainsString((string) $oldFormat, $change->getOldShape());
    }

    public static function dateFormatProvider(): array
    {
        return [
            'date to datetime' => ['strict_date', 'strict_date_hour_minute_second', 'strip'],
            'datetime to date' => ['strict_date_hour_minute_second', 'strict_date', 'strip'],
            'date to time' => ['strict_date', 'strict_hour_minute', 'strip'],
            'respect timezone switched on' => ['strict_date', 'strict_date_time_no_millis', 'strip'],
            'respect timezone switched off' => [
                'strict_date_time_no_millis',
                'strict_date_hour_minute_second',
                'strip',
            ],
            'engine default to a strict format' => [null, 'strict_date', 'strip'],
            'same format' => ['strict_date', 'strict_date', null],
            'format added as alternative' => ['strict_date', 'strict_date||epoch_millis', null],
            'alternatives with spaces' => ['strict_date||epoch_millis', 'epoch_millis || strict_date', null],
        ];
    }

    public function testSeveralDateFieldsWithChangedFormatsAreAllStripped(): void
    {
        $fields = static fn (string $format): array => [
            'd1' => ['type' => 'date', 'format' => $format],
            'd2' => ['type' => 'date', 'format' => $format],
            'd3' => ['type' => 'date', 'format' => $format],
            'd4' => ['type' => 'date', 'format' => $format],
        ];

        $diff = $this->service->diff(
            $this->standardFields($fields('strict_date')),
            $this->standardFields($fields('strict_date_hour_minute_second'))
        );

        $this->assertSame(
            ['standard_fields.d1', 'standard_fields.d2', 'standard_fields.d3', 'standard_fields.d4'],
            $diff->getStripPaths(),
            'All of them in the first attempt: the engine reports only one failing field per document'
        );
    }

    /**
     * Classification store keys and groups may contain dots ("spec.width"). The engine expands such
     * property names into objects, so the live mapping holds spec -> width.
     */
    public function testDottedPropertyNamesAreComparedLikeTheEngineExpandsThem(): void
    {
        $live = $this->standardFields(['cs' => ['properties' => ['default' => ['properties' => [
            'spec' => ['properties' => ['width' => self::TEXT_KEYWORD, 'height' => ['type' => 'float']]],
        ]]]]]);
        $new = $this->standardFields(['cs' => ['properties' => ['default' => ['properties' => [
            'spec.width' => ['type' => 'float'],
            'spec.height' => ['type' => 'float'],
        ]]]]]);

        $diff = $this->service->diff($live, $new);

        $this->assertSame(['standard_fields.cs.default.spec.width'], $diff->getStripPaths());
        $this->assertSame(
            [['standard_fields', 'cs', 'default', 'spec', 'width']],
            $diff->getStripPathSegments()
        );
    }

    public function testUnchangedDottedPropertyNamesHaveNoChanges(): void
    {
        $live = $this->standardFields([
            'a' => ['properties' => ['b' => ['properties' => ['c' => ['type' => 'long']]]]],
        ]);
        $new = $this->standardFields(['a.b.c' => ['type' => 'long']]);

        $this->assertTrue($this->service->diff($live, $new)->isEmpty());
    }

    public function testWithoutRemovedFieldsTheCopyUsesTheNewMappingAsGiven(): void
    {
        $new = $this->standardFields(['title' => self::TEXT_KEYWORD, 'price' => ['type' => 'float']]);

        $copy = $this->service->mappingForCopy($this->standardFields(['title' => self::TEXT_KEYWORD]), $new);

        $this->assertSame($new, $copy->getProperties(), 'Unchanged request when nothing was removed');
        $this->assertSame([], $copy->getCarriedPaths());
    }

    /**
     * A removed field keeps the type its values were indexed with, instead of being mapped again
     * from the first copied document (where "2026-01-01" in one document and "abc" in the next fail).
     */
    public function testRemovedFieldsKeepTheirLiveTypeWithoutAnalyzers(): void
    {
        $live = $this->standardFields([
            'title' => self::TEXT_KEYWORD,
            'old' => [
                'type' => 'text',
                'analyzer' => 'custom_analyzer',
                'fields' => ['sort' => ['type' => 'keyword', 'normalizer' => 'custom', 'ignore_above' => 8191]],
            ],
            'when' => ['type' => 'date', 'format' => 'strict_date'],
        ]);
        $new = $this->standardFields(['title' => self::TEXT_KEYWORD]);

        $copy = $this->service->mappingForCopy($live, $new);

        $this->assertSame(['standard_fields.old', 'standard_fields.when'], $copy->getCarriedPaths());
        $fields = $copy->getProperties()['standard_fields']['properties'];
        $this->assertSame(
            ['type' => 'text', 'fields' => ['sort' => ['type' => 'keyword', 'ignore_above' => 8191]]],
            $fields['old']
        );
        $this->assertSame(['type' => 'date', 'format' => 'strict_date'], $fields['when']);
        $this->assertSame(self::TEXT_KEYWORD, $fields['title']);
    }

    public function testRemovedNestedFieldsAreCarriedAsObjects(): void
    {
        $live = $this->standardFields(['items' => [
            'type' => 'nested',
            'include_in_parent' => true,
            'properties' => ['v' => ['type' => 'long']],
        ]]);

        $copy = $this->service->mappingForCopy($live, $this->standardFields([]));

        $this->assertSame(
            ['properties' => ['v' => ['type' => 'long']]],
            $copy->getProperties()['standard_fields']['properties']['items'],
            'Carried nested fields would count against index.mapping.nested_fields.limit'
        );
    }

    public function testRemovedFieldsInsideKeptContainersAreCarriedAtTheirLevel(): void
    {
        $live = $this->standardFields(['bricks' => ['properties' => ['Brick' => ['properties' => [
            'a' => ['type' => 'long'],
            'gone' => ['type' => 'keyword'],
        ]]]]]);
        $new = $this->standardFields(['bricks' => ['properties' => ['Brick' => ['properties' => [
            'a' => ['type' => 'long'],
        ]]]]]);

        $copy = $this->service->mappingForCopy($live, $new);

        $this->assertSame(['standard_fields.bricks.Brick.gone'], $copy->getCarriedPaths());
        $this->assertSame(
            ['a' => ['type' => 'long'], 'gone' => ['type' => 'keyword']],
            $copy->getProperties()['standard_fields']['properties']['bricks']['properties']['Brick']['properties']
        );
    }

    public function testAFieldThatChangedShapeIsNotCarried(): void
    {
        $live = $this->standardFields(['rel' => ['properties' => ['id' => ['type' => 'long']]]]);
        $new = $this->standardFields(['rel' => ['type' => 'keyword']]);

        $copy = $this->service->mappingForCopy($live, $new);

        $this->assertSame([], $copy->getCarriedPaths());
        $this->assertSame($new, $copy->getProperties());
    }

    public function testRemovedFieldsNextToDottedNamesAreCarriedAsDottedNames(): void
    {
        $live = $this->standardFields(['cs' => ['properties' => ['spec' => ['properties' => [
            'width' => ['type' => 'float'],
            'depth' => ['type' => 'float'],
        ]]]]]);
        $new = $this->standardFields(['cs' => ['properties' => ['spec.width' => ['type' => 'float']]]]);

        $copy = $this->service->mappingForCopy($live, $new);

        $this->assertSame(['standard_fields.cs.spec.depth'], $copy->getCarriedPaths());
        $this->assertSame(
            ['spec.width' => ['type' => 'float'], 'spec.depth' => ['type' => 'float']],
            $copy->getProperties()['standard_fields']['properties']['cs']['properties'],
            'The generated mapping stays as it is; the engine expands both names into spec'
        );
    }

    public function testEnumTypesOfTheNewMappingStayAsGivenWhenFieldsAreCarried(): void
    {
        $live = $this->standardFields(['a' => ['type' => 'long'], 'gone' => ['type' => 'long']]);
        $new = $this->standardFields(['a' => ['type' => AttributeType::LONG]]);

        $copy = $this->service->mappingForCopy($live, $new);

        $this->assertSame(AttributeType::LONG, $copy->getProperties()['standard_fields']['properties']['a']['type']);
        $this->assertSame(['type' => 'long'], $copy->getProperties()['standard_fields']['properties']['gone']);
    }

    public function testParameterOnlyChangesAreIgnored(): void
    {
        $live = $this->standardFields([
            'title' => ['type' => 'text', 'analyzer' => 'standard', 'fields' => ['keyword' => ['type' => 'keyword']]],
            'created' => ['type' => 'date', 'format' => 'strict_date_optional_time'],
        ]);
        $new = $this->standardFields([
            'title' => self::TEXT_KEYWORD,
            'created' => ['type' => 'date'],
        ]);

        $this->assertTrue($this->service->diff($live, $new)->isEmpty());
    }

    public function testAddedAndRemovedFieldsAreNotChanges(): void
    {
        $live = $this->standardFields(['removed' => self::TEXT_KEYWORD, 'kept' => ['type' => 'long']]);
        $new = $this->standardFields(['kept' => ['type' => 'long'], 'added' => ['type' => 'boolean']]);

        $this->assertTrue($this->service->diff($live, $new)->isEmpty());
    }

    /**
     * @dataProvider scalarTransitionProvider
     */
    public function testScalarTransitions(array $old, array $new, MappingFieldChangeAction $expected): void
    {
        $diff = $this->service->diff(
            $this->standardFields(['field' => $old]),
            $this->standardFields(['field' => $new])
        );

        $changes = $diff->getChanges();
        $this->assertCount(1, $changes);
        $this->assertSame('standard_fields.field', $changes[0]->getPath());
        $this->assertSame($expected, $changes[0]->getAction());
    }

    public static function scalarTransitionProvider(): array
    {
        $keep = MappingFieldChangeAction::KEEP;
        $strip = MappingFieldChangeAction::STRIP;

        return [
            // Anything scalar fits into text + keyword (safe list).
            'float -> text' => [['type' => 'float'], self::TEXT_KEYWORD, $keep],
            'long -> keyword' => [['type' => 'long'], ['type' => 'keyword'], $keep],
            'date -> text' => [['type' => 'date'], self::TEXT_KEYWORD, $keep],
            'boolean -> text' => [['type' => 'boolean'], self::TEXT_KEYWORD, $keep],
            'keyword -> text' => [['type' => 'keyword'], self::TEXT_KEYWORD, $keep],
            // Numeric widening (safe list).
            'integer -> long' => [['type' => 'integer'], ['type' => 'long'], $keep],
            'long -> double' => [['type' => 'long'], ['type' => 'double'], $keep],
            'float -> double' => [['type' => 'float'], ['type' => 'double'], $keep],
            // Everything else is stripped.
            'text -> float' => [self::TEXT_KEYWORD, ['type' => 'float'], $strip],
            'text -> date' => [self::TEXT_KEYWORD, ['type' => 'date'], $strip],
            'text -> boolean' => [self::TEXT_KEYWORD, ['type' => 'boolean'], $strip],
            'text -> geo_point' => [self::TEXT_KEYWORD, ['type' => 'geo_point'], $strip],
            'double -> long (narrowing truncates)' => [['type' => 'double'], ['type' => 'long'], $strip],
            'long -> date (fits but read as epoch millis)' => [['type' => 'long'], ['type' => 'date'], $strip],
            'boolean -> long' => [['type' => 'boolean'], ['type' => 'long'], $strip],
            'date -> long' => [['type' => 'date'], ['type' => 'long'], $strip],
            'geo_point -> text (object in source)' => [['type' => 'geo_point'], self::TEXT_KEYWORD, $strip],
            'unknown type -> text' => [['type' => 'flattened'], self::TEXT_KEYWORD, $strip],
        ];
    }

    public function testScalarToObjectAndBackIsStripped(): void
    {
        $relation = ['properties' => ['id' => ['type' => 'long'], 'type' => ['type' => 'keyword']]];

        $toObject = $this->service->diff(
            $this->standardFields(['field' => self::TEXT_KEYWORD]),
            $this->standardFields(['field' => $relation])
        );
        $toScalar = $this->service->diff(
            $this->standardFields(['field' => $relation]),
            $this->standardFields(['field' => self::TEXT_KEYWORD])
        );

        $this->assertSame(['standard_fields.field'], $toObject->getStripPaths());
        $this->assertSame(['standard_fields.field'], $toScalar->getStripPaths());
    }

    public function testObjectToNestedIsKeptAndLeavesAreCompared(): void
    {
        $object = ['properties' => ['id' => ['type' => 'long'], 'name' => self::TEXT_KEYWORD]];
        $nested = [
            'type' => 'nested',
            'properties' => ['id' => ['type' => 'long'], 'name' => ['type' => 'float']],
        ];

        $diff = $this->service->diff(
            $this->standardFields(['field' => $object]),
            $this->standardFields(['field' => $nested])
        );

        $this->assertSame(['standard_fields.field'], $diff->getKeptPaths());
        $this->assertSame(['standard_fields.field.name'], $diff->getStripPaths());
    }

    public function testLocalizedFieldIsComparedPerLanguage(): void
    {
        $live = $this->standardFields([
            'l_text' => ['properties' => ['en' => self::TEXT_KEYWORD, 'de' => self::TEXT_KEYWORD]],
        ]);
        $new = $this->standardFields([
            'l_text' => ['properties' => ['en' => ['type' => 'float'], 'de' => ['type' => 'float']]],
        ]);

        $this->assertSame(
            ['standard_fields.l_text.de', 'standard_fields.l_text.en'],
            $this->service->diff($live, $new)->getStripPaths()
        );
    }

    public function testLeafInsideNestedCollectionIsStrippedAtTheLeaf(): void
    {
        $collection = static fn (array $leaf): array => [
            'type' => 'nested',
            'properties' => ['GdiE2eItem' => ['properties' => ['i_text' => $leaf, 'other' => ['type' => 'long']]]],
        ];

        $diff = $this->service->diff(
            $this->standardFields(['items' => $collection(self::TEXT_KEYWORD)]),
            $this->standardFields(['items' => $collection(['type' => 'float'])])
        );

        $this->assertSame(['standard_fields.items.GdiE2eItem.i_text'], $diff->getStripPaths());
        $this->assertSame([], $diff->getKeptPaths());
    }

    public function testSeveralChangesAreReportedInPathOrder(): void
    {
        $live = $this->standardFields([
            'b' => self::TEXT_KEYWORD,
            'a' => ['type' => 'date'],
            'c' => ['type' => 'long'],
        ]);
        $new = $this->standardFields([
            'b' => ['type' => 'float'],
            'a' => self::TEXT_KEYWORD,
            'c' => ['type' => 'long'],
        ]);

        $diff = $this->service->diff($live, $new);

        $this->assertSame(['standard_fields.b'], $diff->getStripPaths());
        $this->assertSame(['standard_fields.a'], $diff->getKeptPaths());
        $this->assertTrue($diff->hasStripPaths());
    }

    public function testSystemFieldsAndCustomFieldsAreComparedToo(): void
    {
        $live = ['system_fields' => ['properties' => ['id' => ['type' => 'long']]],
            'custom_fields' => ['properties' => ['score' => self::TEXT_KEYWORD]]];
        $new = ['system_fields' => ['properties' => ['id' => ['type' => 'long']]],
            'custom_fields' => ['properties' => ['score' => ['type' => 'double']]]];

        $this->assertSame(['custom_fields.score'], $this->service->diff($live, $new)->getStripPaths());
    }

    private function standardFields(array $fields): array
    {
        return [
            'system_fields' => ['properties' => ['id' => ['type' => 'long']]],
            'standard_fields' => ['properties' => $fields],
        ];
    }
}
