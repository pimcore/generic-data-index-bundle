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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\ClassificationStore;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\StoreConfigListingFactoryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\StoreConfigReader;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupListing;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;

/**
 * A classification store field without a group restriction maps every group of its store, so the group listing
 * is unbounded by configuration: 5,640 groups on the measured ETIM store. It is read in pages, so the number of
 * hydrated models does not grow with the store.
 *
 * The key assignments of one group are deliberately not paged. Measured on that store: the largest group holds
 * 121 assignments, so a page of 200 never splits a batch - it only adds a second, empty query per group, and
 * cost 18 MB of resident models against 8 MB unpaged, with the walk 46 % slower.
 *
 * @internal
 */
final class StoreConfigReaderTest extends Unit
{
    private const STORE_ID = 14;

    private const GROUP_ID = 1431;

    private const PAGE_SIZE = 200;

    private const LIMIT = 'limit';

    private const OFFSET = 'offset';

    /** @var list<array{limit: int|null, offset: int|null}> */
    private array $groupPages = [];

    /** @var list<array{limit: int|null, offset: int|null}> */
    private array $relationPages = [];

    /** @var array<string, mixed> */
    private array $groupOrder = [];

    protected function _before(): void
    {
        $this->groupPages = [];
        $this->relationPages = [];
        $this->groupOrder = [];
    }

    public function testTheGroupsOfAStoreAreReadInPages(): void
    {
        $reader = $this->createReader(groupCount: 450);

        $ids = [];
        foreach ($reader->getGroups(self::STORE_ID) as $group) {
            $ids[] = $group->getId();
        }

        $this->assertCount(450, $ids, 'Every group of the store is yielded');
        $this->assertSame(range(1, 450), $ids, 'In the order of the pages, without a gap or a repetition');
        $this->assertSame(
            [
                [self::LIMIT => self::PAGE_SIZE, self::OFFSET => 0],
                [self::LIMIT => self::PAGE_SIZE, self::OFFSET => self::PAGE_SIZE],
                [self::LIMIT => self::PAGE_SIZE, self::OFFSET => 2 * self::PAGE_SIZE],
            ],
            $this->groupPages,
            'One query per page, and no further query after a page shorter than its limit'
        );
    }

    public function testOnlyOnePageOfGroupsIsHydratedBeforeTheFirstOneIsYielded(): void
    {
        $reader = $this->createReader(groupCount: 450);

        $reader->getGroups(self::STORE_ID)->current();

        $this->assertCount(
            1,
            $this->groupPages,
            'The caller gets the first group before the rest of the store is hydrated'
        );
    }

    public function testTheGroupPagesAreOrderedSoThatNoGroupIsSkippedOrRepeated(): void
    {
        $reader = $this->createReader(groupCount: 450);

        $reader->getGroups(self::STORE_ID)->current();

        $this->assertSame(
            ['orderKey' => 'id', 'order' => 'ASC'],
            $this->groupOrder,
            'Paging a listing without an order is what makes a page skip or repeat a row'
        );
    }

    public function testTheKeyAssignmentsOfAGroupAreReadInOneQuery(): void
    {
        $reader = $this->createReader(groupCount: 1, relationCount: 121);

        $relations = $reader->getKeyRelations(self::GROUP_ID);

        $this->assertCount(121, $relations);
        $this->assertSame(
            [[self::LIMIT => null, self::OFFSET => null]],
            $this->relationPages,
            'The assignments of one group are bounded by the group, so they are read unpaged'
        );
    }

    private function createReader(int $groupCount, int $relationCount = 0): StoreConfigReader
    {
        $factory = $this->makeEmpty(StoreConfigListingFactoryInterface::class, [
            'createGroupListing' => fn (): GroupListing => $this->createGroupListing($groupCount),
            'createKeyGroupRelationListing' => fn (): KeyGroupRelationListing
                => $this->createRelationListing($relationCount),
        ]);

        return new StoreConfigReader($factory, self::PAGE_SIZE);
    }

    private function createGroupListing(int $groupCount): GroupListing
    {
        $limit = null;
        $offset = null;
        $listing = null;

        $listing = $this->makeEmpty(GroupListing::class, [
            'setCondition' => static function () use (&$listing): ?GroupListing {
                return $listing;
            },
            'setOrderKey' => function (array|string $orderKey) use (&$listing): ?GroupListing {
                $this->groupOrder['orderKey'] = $orderKey;

                return $listing;
            },
            'setOrder' => function (array|string $order) use (&$listing): ?GroupListing {
                $this->groupOrder['order'] = $order;

                return $listing;
            },
            'setLimit' => static function (?int $value) use (&$limit, &$listing): ?GroupListing {
                $limit = $value;

                return $listing;
            },
            'setOffset' => static function (int $value) use (&$offset, &$listing): ?GroupListing {
                $offset = $value;

                return $listing;
            },
            'getList' => function () use (&$limit, &$offset, $groupCount): array {
                $this->groupPages[] = [self::LIMIT => $limit, self::OFFSET => $offset];
                $from = ($offset ?? 0) + 1;
                $to = min($groupCount, ($offset ?? 0) + ($limit ?? $groupCount));
                $groups = [];
                for ($id = $from; $id <= $to; $id++) {
                    $groups[] = (new GroupConfig())->setId($id);
                }

                return $groups;
            },
        ]);

        return $listing;
    }

    private function createRelationListing(int $relationCount): KeyGroupRelationListing
    {
        $limit = null;
        $offset = null;
        $listing = null;

        $listing = $this->makeEmpty(KeyGroupRelationListing::class, [
            'addConditionParam' => static function () use (&$listing): ?KeyGroupRelationListing {
                return $listing;
            },
            'setLimit' => static function (?int $value) use (&$limit, &$listing): ?KeyGroupRelationListing {
                $limit = $value;

                return $listing;
            },
            'setOffset' => static function (int $value) use (&$offset, &$listing): ?KeyGroupRelationListing {
                $offset = $value;

                return $listing;
            },
            'getList' => function () use (&$limit, &$offset, $relationCount): array {
                $this->relationPages[] = [self::LIMIT => $limit, self::OFFSET => $offset];
                $relations = [];
                for ($i = 0; $i < $relationCount; $i++) {
                    $relations[] = (new KeyGroupRelation())->setKeyId($i + 1);
                }

                return $relations;
            },
        ]);

        return $listing;
    }
}
