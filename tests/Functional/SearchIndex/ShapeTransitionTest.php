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
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexCopyResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * Copying an index into the other -odd/-even index after a field changed its shape, against the
 * real search engine: every ordered pair of seven representative shapes, plus leaf changes inside
 * an object and inside a nested list.
 *
 * @internal
 */
final class ShapeTransitionTest extends Unit
{
    private const ALIAS_SUFFIX = 'e2e_shape_test';

    private const FIELD = 'f';

    /** Only class fields are stripped, so every changed field lives below standard_fields. */
    private const CLASS_FIELDS = 'standard_fields';

    private const FIELD_PATH = self::CLASS_FIELDS . '.' . self::FIELD;

    private const OTHER_FIELD = 'other';

    private const OTHER_VALUE = 'stays unchanged';

    private const DOCUMENT_IDS = ['1', '2', '3'];

    private const KEEP = 'keep';

    private const STRIP = 'strip';

    /**
     * Changed fields the mapping diff keeps (old => new). Every other change of the shape is stripped.
     */
    private const KEPT_TRANSITIONS = [
        ['numeric', 'text'],
        ['boolean', 'text'],
        ['date', 'text'],
        ['object', 'nested'],
        ['nested', 'object'],
    ];

    /**
     * Container switches: the diff keeps them, whether the engine accepts the documents is recorded
     * by the test (only the copy itself is asserted).
     */
    private const CONTAINER_SWITCHES = [
        ['object', 'nested'],
        ['nested', 'object'],
    ];

    protected IndexTester $tester;

    private string $alias;

    protected function _before(): void
    {
        /** @var SearchIndexConfigServiceInterface $configService */
        $configService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $this->alias = $configService->getIndexPrefix() . self::ALIAS_SUFFIX;
        $this->deleteIndices();
    }

    protected function _after(): void
    {
        $this->deleteIndices();
    }

    /**
     * A1-A3 for every ordered pair of different shapes, A6 for the kept ones.
     *
     * @dataProvider transitionProvider
     */
    public function testShapeTransition(string $oldShape, string $newShape, string $expectedAction): void
    {
        $this->createAliasWithDocuments($this->mapping($oldShape), $this->documentFor($oldShape));

        $result = $this->copy($this->mapping($newShape));

        $this->assertCopiedIntoOtherIndex();
        $this->assertSame(3, $this->tester->countIndexDocuments($this->alias), 'Document count after the copy');

        if (in_array([$oldShape, $newShape], self::CONTAINER_SWITCHES, true)) {
            // The engine accepts an object for a nested field (as a list of one) and a list of
            // objects for an object field, so the copy succeeds in the first attempt and keeps the values.
            $this->assertTrue($result->isSuccessful());
            $this->assertSame(1, $result->getAttempts());
            $this->assertSame([], $result->getStrippedPaths());
            $this->assertSame([self::FIELD_PATH], $result->getKeptPaths());
            foreach ($this->getSources() as $id => $source) {
                $this->assertSame($this->valueFor($oldShape), $this->field($source), "Document {$id}");
                $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
            }

            return;
        }

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->getAttempts(), 'The mapping diff alone must find the field');

        if ($expectedAction === self::STRIP) {
            $this->assertSame([self::FIELD_PATH], $result->getStrippedPaths());
            foreach ($this->getSources() as $id => $source) {
                $this->assertArrayNotHasKey(
                    self::FIELD,
                    $source[self::CLASS_FIELDS] ?? [],
                    sprintf('Document %s still has the field', $id)
                );
                $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
            }

            return;
        }

        $this->assertSame([], $result->getStrippedPaths(), 'A kept field must not be stripped');
        $this->assertSame([self::FIELD_PATH], $result->getKeptPaths());
        foreach ($this->getSources() as $id => $source) {
            $this->assertSame(
                $this->valueFor($oldShape),
                $this->field($source),
                sprintf('Document %s lost its old value', $id)
            );
            $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
        }
    }

    public static function transitionProvider(): array
    {
        $cases = [];
        foreach (array_keys(self::shapes()) as $oldShape) {
            foreach (array_keys(self::shapes()) as $newShape) {
                if ($oldShape === $newShape) {
                    continue;
                }
                $action = in_array([$oldShape, $newShape], self::KEPT_TRANSITIONS, true) ? self::KEEP : self::STRIP;
                $cases[sprintf('%s -> %s', $oldShape, $newShape)] = [$oldShape, $newShape, $action];
            }
        }

        return $cases;
    }

    /**
     * A6: an unchanged mapping copies like the plain copy, nothing is stripped or reported as kept.
     *
     * @dataProvider shapeProvider
     */
    public function testUnchangedMappingStripsNothing(string $shape): void
    {
        $this->createAliasWithDocuments($this->mapping($shape), $this->documentFor($shape));

        $result = $this->copy($this->mapping($shape));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([], $result->getStrippedPaths());
        $this->assertSame([], $result->getKeptPaths());
        $this->assertCopiedIntoOtherIndex();
        $this->assertSame(3, $this->tester->countIndexDocuments($this->alias));
        foreach ($this->getSources() as $source) {
            $this->assertSame($this->valueFor($shape), $this->field($source));
        }
    }

    public static function shapeProvider(): array
    {
        $cases = [];
        foreach (array_keys(self::shapes()) as $shape) {
            $cases[$shape] = [$shape];
        }

        return $cases;
    }

    /**
     * S43: leaves of an object change their type; the kept leaf keeps its value, the stripped leaf
     * is removed without touching the rest of the object.
     */
    public function testObjectLeafChanges(): void
    {
        $oldMapping = [
            self::CLASS_FIELDS => ['properties' => [
                self::FIELD => ['properties' => ['id' => ['type' => 'long'], 'name' => ['type' => 'text']]],
            ]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ];
        $newMapping = [
            self::CLASS_FIELDS => ['properties' => [
                self::FIELD => ['properties' => ['id' => ['type' => 'keyword'], 'name' => ['type' => 'float']]],
            ]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ];
        $this->createAliasWithDocuments(
            $oldMapping,
            [
                self::CLASS_FIELDS => [self::FIELD => ['id' => 5, 'name' => 'abc']],
                self::OTHER_FIELD => self::OTHER_VALUE,
            ]
        );

        $result = $this->copy($newMapping);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([self::FIELD_PATH . '.name'], $result->getStrippedPaths());
        $this->assertSame([self::FIELD_PATH . '.id'], $result->getKeptPaths());
        $this->assertCopiedIntoOtherIndex();
        $this->assertSame(3, $this->tester->countIndexDocuments($this->alias));
        foreach ($this->getSources() as $source) {
            $this->assertSame(['id' => 5], $this->field($source));
            $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
        }
    }

    /**
     * S44: a leaf inside a nested list (the layout of field collections, items[].Type.field) is
     * removed from every item; the other leaves and the number of items stay.
     */
    /**
     * S45: a classification store key (or group) with a dot in its name stays one literal key in the
     * document ("spec.width"), while the engine maps it as spec -> width. The diff finds it and the
     * strip script removes the literal key.
     */
    public function testLiteralDottedKeyIsStripped(): void
    {
        $storeMapping = static fn (string $type): array => [
            self::CLASS_FIELDS => ['properties' => [
                'cs' => ['properties' => ['spec.width' => ['type' => $type], 'other' => ['type' => 'keyword']]],
            ]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ];
        $this->createAliasWithDocuments(
            $storeMapping('text'),
            [
                self::CLASS_FIELDS => ['cs' => ['spec.width' => 'abc', 'other' => 'x']],
                self::OTHER_FIELD => self::OTHER_VALUE,
            ]
        );

        $result = $this->copy($storeMapping('float'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->getAttempts(), 'The diff must find the dotted key, not the retry');
        $this->assertSame([self::CLASS_FIELDS . '.cs.spec.width'], $result->getStrippedPaths());
        $this->assertCopiedIntoOtherIndex();
        foreach ($this->getSources() as $source) {
            $this->assertSame(['other' => 'x'], $source[self::CLASS_FIELDS]['cs']);
            $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
        }
    }

    /**
     * S46: a sub-field (multi-field) of another type fails in the copy and the engine names it
     * (title.num). The sub-field exists only in the mapping; its field is stripped, whether the
     * document holds one value or a list.
     */
    public function testFailingSubFieldStripsItsField(): void
    {
        $withSubField = static fn (array $fields): array => [
            'standard_fields' => ['properties' => [
                'title' => ['type' => 'text', 'fields' => $fields],
                'tags' => ['type' => 'text', 'fields' => $fields],
            ]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ];
        $this->createAliasWithDocuments(
            $withSubField(['keyword' => ['type' => 'keyword']]),
            ['standard_fields' => ['title' => 'abc', 'tags' => ['abc', 'def']], self::OTHER_FIELD => self::OTHER_VALUE]
        );

        $result = $this->copy($withSubField(['num' => ['type' => 'long']]));

        $this->assertTrue($result->isSuccessful());
        $this->assertContains('standard_fields.title', $result->getStrippedPaths());
        $this->assertContains('standard_fields.tags', $result->getStrippedPaths());
        $this->assertCopiedIntoOtherIndex();
        foreach ($this->getSources() as $source) {
            $this->assertSame([], $source['standard_fields']);
            $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
        }
    }

    /**
     * S47: the engine maps removed fields again from the first copied document. "2026-01-01" makes
     * a removed text field a date, and "abc" in the next document fails - one field per attempt. A
     * failure on one removed field strips all of them, so four such fields need two attempts.
     */
    public function testConflictingRemovedFieldsAreStrippedTogether(): void
    {
        $removed = ['gone1', 'gone2', 'gone3', 'gone4'];
        $this->createAliasWithDocuments(
            [
                'standard_fields' => ['properties' => [
                    ...array_fill_keys($removed, ['type' => 'text']),
                    'kept' => ['type' => 'keyword'],
                ]],
                'system_fields' => ['properties' => ['classId' => ['type' => 'keyword']]],
                self::OTHER_FIELD => ['type' => 'keyword'],
            ],
            static fn (string $id): array => [
                'standard_fields' => [
                    ...array_fill_keys($removed, $id === self::DOCUMENT_IDS[0] ? '2026-01-01' : 'abc-' . $id),
                    'kept' => 'k',
                ],
                'system_fields' => ['classId' => 'C1'],
                self::OTHER_FIELD => self::OTHER_VALUE,
            ]
        );

        // Neither system_fields.classId nor the other field is in the new mapping (the engine maps
        // some system fields dynamically): only the removed class fields may be stripped.
        $result = $this->copy(['standard_fields' => ['properties' => ['kept' => ['type' => 'keyword']]]]);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2, $result->getAttempts());
        $this->assertEqualsCanonicalizing(
            array_map(static fn (string $field): string => 'standard_fields.' . $field, $removed),
            $result->getStrippedPaths()
        );
        $this->assertCopiedIntoOtherIndex();
        foreach ($this->getSources() as $source) {
            $this->assertSame(['kept' => 'k'], $source['standard_fields']);
            $this->assertSame(['classId' => 'C1'], $source['system_fields']);
            $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
        }
    }

    /**
     * S48: the parent of a dotted key ("spec" of "spec.width") exists only in the mapping. When the
     * parent changes from an object to a scalar, stripping it removes the literal dotted keys.
     */
    public function testDottedParentChangedToScalarStripsTheDottedKeys(): void
    {
        $this->createAliasWithDocuments(
            [
                self::CLASS_FIELDS => ['properties' => [
                    'cs' => ['properties' => ['spec.width' => ['type' => 'text'], 'other' => ['type' => 'keyword']]],
                ]],
                self::OTHER_FIELD => ['type' => 'keyword'],
            ],
            [
                self::CLASS_FIELDS => ['cs' => ['spec.width' => 'abc', 'spec.depth' => 'def', 'other' => 'x']],
                self::OTHER_FIELD => self::OTHER_VALUE,
            ]
        );

        $result = $this->copy([
            self::CLASS_FIELDS => ['properties' => [
                'cs' => ['properties' => ['spec' => ['type' => 'keyword'], 'other' => ['type' => 'keyword']]],
            ]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->getAttempts());
        $this->assertSame([self::CLASS_FIELDS . '.cs.spec'], $result->getStrippedPaths());
        foreach ($this->getSources() as $source) {
            $this->assertSame(['other' => 'x'], $source[self::CLASS_FIELDS]['cs']);
        }
    }

    /**
     * S49: all spec.* keys of a classification store were removed and their values conflict when the
     * engine maps them again; the removed parent "spec" is stripped together with its dotted keys.
     */
    public function testRemovedDottedParentStripsTheDottedKeys(): void
    {
        $this->createAliasWithDocuments(
            [
                'standard_fields' => ['properties' => ['cs' => ['properties' => [
                    'spec.width' => ['type' => 'text'],
                    'spec.depth' => ['type' => 'text'],
                    'other' => ['type' => 'keyword'],
                ]]]],
                self::OTHER_FIELD => ['type' => 'keyword'],
            ],
            static fn (string $id): array => [
                'standard_fields' => ['cs' => [
                    'spec.width' => $id === self::DOCUMENT_IDS[0] ? '2026-01-01' : 'abc-' . $id,
                    'spec.depth' => $id === self::DOCUMENT_IDS[0] ? '2026-02-01' : 'def-' . $id,
                    'other' => 'x',
                ]],
                self::OTHER_FIELD => self::OTHER_VALUE,
            ]
        );

        $result = $this->copy([
            'standard_fields' => ['properties' => ['cs' => ['properties' => ['other' => ['type' => 'keyword']]]]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2, $result->getAttempts());
        $this->assertContains('standard_fields.cs.spec', $result->getStrippedPaths());
        foreach ($this->getSources() as $source) {
            $this->assertSame(['other' => 'x'], $source['standard_fields']['cs']);
        }
    }

    /**
     * S50: a document whose rebuilt version is identical to the indexed one is skipped by the
     * checksum comparison of the index service. A stripped copy therefore removes the stored
     * checksum, so the rebuild writes every document; a plain copy keeps it.
     */
    public function testStrippedCopyRemovesTheStoredChecksum(): void
    {
        $document = [
            'standard_fields' => ['f' => 'abc', 'g' => 'keep'],
            'system_fields' => ['id' => 1, 'checksum' => 12345],
        ];
        $mapping = static fn (string $type): array => [
            'standard_fields' => ['properties' => ['f' => ['type' => $type], 'g' => ['type' => 'keyword']]],
            'system_fields' => ['properties' => ['id' => ['type' => 'long'], 'checksum' => ['type' => 'long']]],
        ];
        $this->createAliasWithDocuments($mapping('text'), $document);

        $plain = $this->copy($mapping('text'));
        $this->assertTrue($plain->isSuccessful());
        $this->assertSame([], $plain->getStrippedPaths());
        foreach ($this->getSources() as $source) {
            $this->assertSame(12345, $source['system_fields']['checksum'], 'A plain copy keeps the checksum');
        }

        $stripped = $this->copy($mapping('float'));
        $this->assertTrue($stripped->isSuccessful());
        $this->assertSame(['standard_fields.f'], $stripped->getStrippedPaths());
        foreach ($this->getSources() as $source) {
            $this->assertSame(['g' => 'keep'], $source['standard_fields']);
            $this->assertSame(['id' => 1], $source['system_fields'], 'The checksum is gone, the id stays');
        }
    }

    /**
     * S51: a changed system field is never stripped, not even in the first attempt; search results
     * need the id. When its values fit the new type, the copy succeeds with them.
     */
    public function testChangedSystemFieldIsCopiedAsItIs(): void
    {
        $mapping = static fn (string $idType): array => [
            'system_fields' => ['properties' => ['id' => ['type' => $idType]]],
            self::CLASS_FIELDS => ['properties' => [self::FIELD => ['type' => 'keyword']]],
        ];
        $this->createAliasWithDocuments(
            $mapping('long'),
            static fn (string $id): array => [
                'system_fields' => ['id' => (int) $id],
                self::CLASS_FIELDS => [self::FIELD => 'x'],
            ]
        );

        $result = $this->copy($mapping('integer'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([], $result->getStrippedPaths());
        $this->assertCopiedIntoOtherIndex();
        foreach ($this->getSources() as $id => $source) {
            $this->assertSame((int) $id, $source['system_fields']['id']);
        }
    }

    /**
     * S52: a changed custom field whose values do not fit is stripped like a class field; the
     * indexing subscribers fill it again during the rebuild.
     */
    public function testIncompatibleCustomFieldIsStripped(): void
    {
        $mapping = static fn (string $type): array => [
            'custom_fields' => ['properties' => ['x' => ['type' => $type], 'y' => ['type' => 'keyword']]],
            self::CLASS_FIELDS => ['properties' => [self::FIELD => ['type' => 'keyword']]],
        ];
        $this->createAliasWithDocuments(
            $mapping('keyword'),
            ['custom_fields' => ['x' => 'abc', 'y' => 'stays'], self::CLASS_FIELDS => [self::FIELD => 'x']]
        );

        $result = $this->copy($mapping('long'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->getAttempts());
        $this->assertSame(['custom_fields.x'], $result->getStrippedPaths());
        $this->assertCopiedIntoOtherIndex();
        foreach ($this->getSources() as $source) {
            $this->assertSame(['y' => 'stays'], $source['custom_fields']);
        }
    }

    /**
     * S53: a changed system field whose values do not fit is not stripped; the copy fails and the
     * caller falls back to the recreation, as for any failure no strippable field explains.
     */
    public function testIncompatibleSystemFieldFailsTheCopyInsteadOfBeingStripped(): void
    {
        $mapping = static fn (string $type): array => [
            'system_fields' => ['properties' => ['key' => ['type' => $type]]],
            self::CLASS_FIELDS => ['properties' => [self::FIELD => ['type' => 'keyword']]],
        ];
        $this->createAliasWithDocuments(
            $mapping('keyword'),
            ['system_fields' => ['key' => 'abc'], self::CLASS_FIELDS => [self::FIELD => 'x']]
        );

        $result = $this->copy($mapping('long'));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame([], $result->getStrippedPaths());
        $this->assertSame(
            [$this->alias . '-' . DefaultSearchService::INDEX_VERSION_ODD],
            $this->tester->getIndexAliasTargets($this->alias),
            'The live index stays untouched by the failed copy'
        );
        $this->assertSame(3, $this->tester->countIndexDocuments($this->alias));
    }

    /**
     * S55: a large classification store: 1,000 of 2,000 keys of one object change at once. The strip script walks
     * every key of the object once, independent of the number of stripped paths.
     */
    public function testManyStrippedKeysOfOneObject(): void
    {
        $keys = array_map(static fn (int $i): string => 'k' . $i, range(1, 2000));
        $stripped = array_slice($keys, 0, 1000);
        $mapping = static fn (string $changedType): array => [
            self::CLASS_FIELDS => ['properties' => ['cs' => ['properties' => array_combine(
                $keys,
                array_map(
                    static fn (string $key): array => [
                        'type' => in_array($key, $stripped, true) ? $changedType : 'keyword',
                    ],
                    $keys
                )
            )]]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ];
        $values = array_combine($keys, array_map(static fn (string $key): string => 'v-' . $key, $keys));
        $this->createAliasWithDocuments(
            $mapping('keyword'),
            [self::CLASS_FIELDS => ['cs' => $values], self::OTHER_FIELD => self::OTHER_VALUE]
        );

        $result = $this->copy($mapping('long'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->getAttempts());
        $this->assertCount(1000, $result->getStrippedPaths());
        $this->assertCopiedIntoOtherIndex();
        $expected = array_slice($values, 1000, null, true);
        foreach ($this->getSources() as $source) {
            $actual = $source[self::CLASS_FIELDS]['cs'];
            ksort($actual, SORT_STRING);
            ksort($expected, SORT_STRING);
            $this->assertSame($expected, $actual, 'The other 1,000 keys keep their values (in any order)');
        }
    }

    public function testNestedLeafIsStrippedInEveryItem(): void
    {
        $itemMapping = static fn (string $leafType): array => [
            self::CLASS_FIELDS => ['properties' => [
                'items' => [
                    'type' => 'nested',
                    'properties' => [
                        'T' => [
                            'type' => 'nested',
                            'properties' => ['v' => ['type' => $leafType], 'w' => ['type' => 'long']],
                        ],
                    ],
                ],
            ]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ];
        $this->createAliasWithDocuments(
            $itemMapping('text'),
            [
                self::CLASS_FIELDS => [
                    'items' => [['T' => ['v' => 'abc', 'w' => 1]], ['T' => ['v' => 'def', 'w' => 2]]],
                ],
                self::OTHER_FIELD => self::OTHER_VALUE,
            ]
        );

        $result = $this->copy($itemMapping('float'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([self::CLASS_FIELDS . '.items.T.v'], $result->getStrippedPaths());
        $this->assertCopiedIntoOtherIndex();
        $this->assertSame(3, $this->tester->countIndexDocuments($this->alias));
        foreach ($this->getSources() as $source) {
            $this->assertSame([['T' => ['w' => 1]], ['T' => ['w' => 2]]], $source[self::CLASS_FIELDS]['items']);
            $this->assertSame(self::OTHER_VALUE, $source[self::OTHER_FIELD]);
        }
    }

    /**
     * One representative mapping and value per shape.
     *
     * @return array<string, array{mapping: array, value: mixed}>
     */
    private static function shapes(): array
    {
        return [
            'text' => [
                'mapping' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'value' => 'abc',
            ],
            'numeric' => ['mapping' => ['type' => 'float'], 'value' => 12.5],
            'boolean' => ['mapping' => ['type' => 'boolean'], 'value' => true],
            'date' => ['mapping' => ['type' => 'date'], 'value' => '2026-01-01T00:00:00+00:00'],
            'geo_point' => ['mapping' => ['type' => 'geo_point'], 'value' => ['lat' => 47.1, 'lon' => 11.2]],
            'object' => ['mapping' => ['properties' => ['id' => ['type' => 'long']]], 'value' => ['id' => 5]],
            'nested' => [
                'mapping' => ['type' => 'nested', 'properties' => ['id' => ['type' => 'long']]],
                'value' => [['id' => 1], ['id' => 2]],
            ],
        ];
    }

    private function mapping(string $shape): array
    {
        return [
            self::CLASS_FIELDS => ['properties' => [self::FIELD => self::shapes()[$shape]['mapping']]],
            self::OTHER_FIELD => ['type' => 'keyword'],
        ];
    }

    private function valueFor(string $shape): mixed
    {
        return self::shapes()[$shape]['value'];
    }

    private function documentFor(string $shape): array
    {
        return [self::CLASS_FIELDS => [self::FIELD => $this->valueFor($shape)], self::OTHER_FIELD => self::OTHER_VALUE];
    }

    private function field(array $source): mixed
    {
        return $source[self::CLASS_FIELDS][self::FIELD] ?? null;
    }

    /**
     * The live index is the -odd one, so the copy has to switch the alias to -even.
     */
    /**
     * @param array|callable(string): array $document the same document for every id, or one per id
     */
    private function createAliasWithDocuments(array $mapping, array|callable $document): void
    {
        $indexName = $this->alias . '-' . DefaultSearchService::INDEX_VERSION_ODD;
        $this->getSearchIndexService()->createIndex($indexName, $mapping)->addAlias($this->alias, $indexName);
        $this->assertSame([$indexName], $this->tester->getIndexAliasTargets($this->alias));

        $client = $this->getClient();
        foreach (self::DOCUMENT_IDS as $id) {
            $body = is_callable($document) ? $document($id) : $document;
            $client->index(['index' => $this->alias, 'id' => $id, 'body' => $body, 'refresh' => true]);
        }
        $this->assertSame(3, $this->tester->countIndexDocuments($this->alias), 'Documents of the old shape');
    }

    private function copy(array $mapping): IndexCopyResult
    {
        /** @var IndexCopyServiceInterface $copyService */
        $copyService = $this->tester->grabService(IndexCopyServiceInterface::class);

        return $copyService->copy($this->alias, $mapping);
    }

    /**
     * A1: the alias was moved to the other index, the live index was not recreated in place.
     */
    private function assertCopiedIntoOtherIndex(): void
    {
        $this->assertSame(
            [$this->alias . '-' . DefaultSearchService::INDEX_VERSION_EVEN],
            $this->tester->getIndexAliasTargets($this->alias)
        );
        $this->assertFalse(
            $this->getSearchIndexService()->existsIndex($this->alias . '-' . DefaultSearchService::INDEX_VERSION_ODD),
            'The old index is removed after the alias switch'
        );
    }

    /**
     * @return array<string, array> _source per document id
     */
    private function getSources(): array
    {
        $sources = [];
        foreach (self::DOCUMENT_IDS as $id) {
            $sources[$id] = $this->tester->checkIndexEntry($id, $this->alias)['_source'];
        }

        return $sources;
    }

    private function deleteIndices(): void
    {
        $searchIndexService = $this->getSearchIndexService();
        foreach ([DefaultSearchService::INDEX_VERSION_ODD, DefaultSearchService::INDEX_VERSION_EVEN] as $version) {
            $searchIndexService->deleteIndex($this->alias . '-' . $version, true);
        }
        // Writes through a missing alias would have auto-created an index with the alias name.
        $searchIndexService->deleteIndex($this->alias, true);
    }

    private function getSearchIndexService(): SearchIndexServiceInterface
    {
        return $this->tester->grabService(SearchIndexServiceInterface::class);
    }

    private function getClient(): SearchClientInterface
    {
        return $this->tester->getIndexSearchClient();
    }
}
