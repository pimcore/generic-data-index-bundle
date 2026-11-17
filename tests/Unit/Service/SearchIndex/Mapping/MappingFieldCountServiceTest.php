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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingFieldCountService;

/**
 * The counting rule is the one measured against OpenSearch 2.19.3 with eight throwaway indices created at
 * "total_fields.limit: 10": counted fields are the leaves plus the object and nested nodes plus the
 * sub-fields of a multi-field. A leaf-only count understates a classification store mapping by ~2.3x.
 *
 * @internal
 */
final class MappingFieldCountServiceTest extends Unit
{
    private const PROPERTIES = 'properties';

    private const FIELDS = 'fields';

    private const KEYWORD = 'keyword';

    private const STANDARD_FIELDS = 'standard_fields';

    private const TYPE = 'type';

    private const TEXT = 'text';

    private const DOUBLE = 'double';

    private const NESTED = 'nested';

    private const DYNAMIC = 'dynamic';

    public function testFlatLeavesAreCountedOnce(): void
    {
        $this->assertSame(10, $this->service()->countFields($this->leaves(10)));
        $this->assertSame(11, $this->service()->countFields($this->leaves(11)));
    }

    public function testAnObjectNodeIsCountedOnTopOfItsLeaves(): void
    {
        $mapping = [
            'group' => [self::PROPERTIES => $this->leaves(9)],
        ];

        $this->assertSame(10, $this->service()->countFields($mapping));
    }

    public function testANestedNodeIsCountedOnTopOfItsLeaves(): void
    {
        $mapping = [
            'store' => [
                self::TYPE => self::NESTED,
                self::DYNAMIC => false,
                self::PROPERTIES => $this->leaves(9),
            ],
        ];

        $this->assertSame(10, $this->service()->countFields($mapping));
    }

    public function testMultiFieldSubFieldsAreCounted(): void
    {
        // five text leaves with one keyword sub-field each: the engine accepted exactly this at limit 10
        $mapping = [];
        for ($i = 0; $i < 5; $i++) {
            $mapping['text' . $i] = [
                self::TYPE => self::TEXT,
                self::FIELDS => [
                    self::KEYWORD => [self::TYPE => self::KEYWORD],
                ],
            ];
        }

        $this->assertSame(10, $this->service()->countFields($mapping));
    }

    /**
     * A classification store field, at one group and the key types of the measured ETIM store: the shape the
     * live 193,019 decomposes from - group node + language node + 1 (numeric) + 5 (select, text with four
     * sub-fields) + 1 (booleanSelect) + 4 (quantityValueRange, object with three leaves) - plus the nested root.
     */
    public function testAClassificationStoreGroupIsCountedLikeTheMeasuredMapping(): void
    {
        $mapping = [
            'technicalAttributes' => [
                self::TYPE => self::NESTED,
                self::DYNAMIC => false,
                self::PROPERTIES => [
                    'GROUP1' => [
                        self::PROPERTIES => [
                            'default' => [
                                self::PROPERTIES => [
                                    'numericKey' => [self::TYPE => self::DOUBLE],
                                    'selectKey' => [
                                        self::TYPE => self::TEXT,
                                        self::FIELDS => [
                                            'analyzed' => [self::TYPE => self::TEXT],
                                            'analyzed_ngram' => [self::TYPE => self::TEXT],
                                            self::KEYWORD => [self::TYPE => self::KEYWORD],
                                            'sort' => [self::TYPE => self::KEYWORD],
                                        ],
                                    ],
                                    'booleanKey' => [self::TYPE => 'boolean'],
                                    'rangeKey' => [
                                        self::PROPERTIES => [
                                            'value' => [self::TYPE => self::DOUBLE],
                                            'minimum' => [self::TYPE => self::DOUBLE],
                                            'maximum' => [self::TYPE => self::DOUBLE],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(1 + 1 + 1 + 1 + 5 + 1 + 4, $this->service()->countFields($mapping));
    }

    public function testTheLargestContributorNamesTheClassFieldAndItsShare(): void
    {
        $mapping = [
            'system_fields' => [self::PROPERTIES => $this->leaves(3)],
            self::STANDARD_FIELDS => [
                self::DYNAMIC => false,
                self::PROPERTIES => [
                    'name' => [self::TYPE => self::KEYWORD],
                    'technicalAttributes' => [
                        self::TYPE => self::NESTED,
                        self::PROPERTIES => $this->leaves(40),
                    ],
                ],
            ],
        ];

        $largest = $this->service()->getLargestContributor($mapping);

        $this->assertNotNull($largest);
        $this->assertSame(self::STANDARD_FIELDS . '.technicalAttributes', $largest['path']);
        $this->assertSame(41, $largest['fields']);
    }

    public function testAnEmptyMappingHasNoContributorAndNoFields(): void
    {
        $this->assertSame(0, $this->service()->countFields([]));
        $this->assertNull($this->service()->getLargestContributor([]));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function leaves(int $count): array
    {
        $leaves = [];
        for ($i = 0; $i < $count; $i++) {
            $leaves['leaf' . $i] = [self::TYPE => self::KEYWORD];
        }

        return $leaves;
    }

    private function service(): MappingFieldCountService
    {
        return new MappingFieldCountService();
    }
}
