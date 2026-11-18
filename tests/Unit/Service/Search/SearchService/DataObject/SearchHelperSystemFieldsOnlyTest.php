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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchService\DataObject;

use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Source\SystemFieldsOnlySource;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\SearchHelper;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DataObjectTypeSerializationHandlerService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DataObjectSearchResultDenormalizer;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Covers the hydration of data object search hits with and without the SystemFieldsOnlySource modifier.
 *
 * @internal
 */
final class SearchHelperSystemFieldsOnlyTest extends Unit
{
    private const OBJECT_ID = 42;

    private DataObjectSearchServiceInterface&MockObject $dataObjectSearchService;

    private PermissionServiceInterface&MockObject $permissionService;

    private RuntimeCacheResolverInterface&MockObject $runtimeCacheResolver;

    private User $user;

    protected function setUp(): void
    {
        $this->dataObjectSearchService = $this->createMock(DataObjectSearchServiceInterface::class);
        $this->permissionService = $this->createMock(PermissionServiceInterface::class);
        $this->permissionService->method('getDataObjectPermissions')->willReturn(new DataObjectPermissions());
        $this->runtimeCacheResolver = $this->createMock(RuntimeCacheResolverInterface::class);
        $this->user = (new User())->setId(7);
    }

    public function testWithoutTheModifierItemsCarryTheFullSearchIndexDataAndAreCached(): void
    {
        $this->dataObjectSearchService->expects($this->never())->method('byId');
        $this->runtimeCacheResolver->expects($this->once())->method('save');

        $item = $this->hydrate($this->fullSource(), $this->search(false));

        $this->assertSame($this->expectedFullSearchIndexData(), $item->getSearchIndexData());
        $this->assertTrue($item->isHasChildren());
        $this->assertTrue($item->isHasWorkflowWithPermissions());
    }

    public function testSystemFieldsOnlyItemsKeepTheirFlagsWithoutLazyLoading(): void
    {
        $this->dataObjectSearchService->expects($this->never())->method('byId');
        $this->dataObjectSearchService->expects($this->never())->method('search');

        $item = $this->hydrate($this->systemFieldsOnlySource(), $this->search(true));

        $this->assertSame(self::OBJECT_ID, $item->getId());
        $this->assertSame('/test/test-object', $item->getFullPath());
        $this->assertTrue($item->isHasChildren());
        $this->assertTrue($item->isHasWorkflowWithPermissions());
        $this->assertCount(1, $item->getInheritedFields());
        $this->assertSame('name', $item->getInheritedFields()[0]->getKey());
        $this->assertSame(5, $item->getInheritedFields()[0]->getOriginId());
    }

    public static function permissionTypes(): array
    {
        return [
            'list' => [PermissionTypes::LIST],
            'view' => [PermissionTypes::VIEW],
        ];
    }

    /**
     * The full document is searched with the user and the permission type of the originating search, by id,
     * and not through byId(), which requires the VIEW permission and serves items from the runtime cache.
     *
     * @dataProvider permissionTypes
     */
    public function testSystemFieldsOnlyItemsLazyLoadTheFullDataWithTheUserAndPermissionTypeOfTheSearch(
        PermissionTypes $permissionType
    ): void {
        $this->dataObjectSearchService->expects($this->never())->method('byId');
        $this->dataObjectSearchService
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(
                function (DataObjectSearchInterface $search, PermissionTypes $usedPermissionType) use (
                    $permissionType
                ): DataObjectSearchResult {
                    $this->assertSame($permissionType, $usedPermissionType);
                    $this->assertSame($this->user, $search->getUser());
                    $this->assertSame(1, $search->getPageSize());
                    $this->assertEquals([new IdFilter(self::OBJECT_ID)], $search->getModifiers());

                    return $this->fullSearchResult();
                }
            );

        $item = $this->hydrate($this->systemFieldsOnlySource(), $this->search(true), $permissionType);

        $this->assertSame($this->fullSource(), $item->getSearchIndexData());
        // The lazy-loaded data stays on the item, a second access does not search again.
        $this->assertSame($this->fullSource(), $item->getSearchIndexData());
    }

    /**
     * The permission event passes the result item to its listeners. A listener that reads the search
     * index data must get the full document, not only the system fields of the hit.
     */
    public function testPermissionListenersReadTheFullSearchIndexDataOfAListOnlyHit(): void
    {
        // byId() requires the VIEW permission and finds nothing for a hit the user may only list.
        $this->dataObjectSearchService->method('byId')->willReturn(null);
        $this->dataObjectSearchService
            ->method('search')
            ->willReturnCallback(
                fn (DataObjectSearchInterface $search, PermissionTypes $permissionType): DataObjectSearchResult =>
                    $permissionType === PermissionTypes::LIST
                        ? $this->fullSearchResult()
                        : $this->emptySearchResult()
            );

        $dataSeenByListener = null;
        $this->permissionService = $this->createMock(PermissionServiceInterface::class);
        $this->permissionService
            ->method('getDataObjectPermissions')
            ->willReturnCallback(
                static function (DataObjectSearchResultItem $item) use (&$dataSeenByListener): DataObjectPermissions {
                    $dataSeenByListener = $item->getSearchIndexData();

                    return new DataObjectPermissions();
                }
            );

        $this->hydrate($this->systemFieldsOnlySource(), $this->search(true), PermissionTypes::LIST);

        $this->assertSame($this->fullSource(), $dataSeenByListener);
    }

    /**
     * byId() serves items from the runtime cache. A cached system fields only item would be returned
     * to its own lazy-loading handler and to every later lookup of the same data object.
     */
    public function testSystemFieldsOnlyItemsAreNotSavedInTheRuntimeCache(): void
    {
        $this->runtimeCacheResolver->expects($this->never())->method('save');

        $this->hydrate($this->systemFieldsOnlySource(), $this->search(true));
    }

    private function hydrate(
        array $source,
        DataObjectSearch $search,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): DataObjectSearchResultItem {
        $hit = new SearchResultHit(
            id: (string) self::OBJECT_ID,
            index: 'pimcore_car',
            score: 1.0,
            source: $source,
            sort: null,
        );

        $items = $this->searchHelper()->hydrateDataObjectSearchResultHits(
            new SearchResult(
                hits: [$hit],
                aggregations: [],
                totalHits: 1,
                maxScore: 1.0,
                search: new Search(),
                response: [],
            ),
            [self::OBJECT_ID => 3],
            $search,
            $permissionType
        );

        $this->assertCount(1, $items);

        return $items[0];
    }

    private function searchHelper(): SearchHelper
    {
        return new SearchHelper(
            dataObjectSearchService: $this->dataObjectSearchService,
            denormalizer: new DataObjectSearchResultDenormalizer(
                new DataObjectTypeSerializationHandlerService(new ServiceLocator([]))
            ),
            logger: $this->createMock(LoggerInterface::class),
            permissionService: $this->permissionService,
            runtimeCacheResolver: $this->runtimeCacheResolver,
            searchIndexService: $this->createMock(SearchIndexServiceInterface::class),
            searchModifierService: $this->createMock(SearchModifierServiceInterface::class),
            searchProvider: $this->createConfiguredMock(SearchProviderInterface::class, [
                'createDataObjectSearch' => new DataObjectSearch(),
            ]),
            userPermissionService: $this->createMock(UserPermissionServiceInterface::class),
        );
    }

    private function search(bool $systemFieldsOnly): DataObjectSearch
    {
        $search = new DataObjectSearch();
        $search->setUser($this->user);
        if ($systemFieldsOnly) {
            $search->addModifier(new SystemFieldsOnlySource());
        }

        return $search;
    }

    private function fullItem(): DataObjectSearchResultItem
    {
        return (new DataObjectSearchResultItem())
            ->setId(self::OBJECT_ID)
            ->setHasChildren(true)
            ->setHasWorkflowWithPermissions(true)
            ->setSearchIndexData($this->fullSource());
    }

    private function fullSearchResult(): DataObjectSearchResult
    {
        return new DataObjectSearchResult(
            items: [$this->fullItem()],
            pagination: new PaginationInfo(totalItems: 1, page: 1, pageSize: 1, totalPages: 1),
        );
    }

    private function emptySearchResult(): DataObjectSearchResult
    {
        return new DataObjectSearchResult(
            items: [],
            pagination: new PaginationInfo(totalItems: 0, page: 1, pageSize: 1, totalPages: 0),
        );
    }

    private function systemFieldsOnlySource(): array
    {
        return [
            'system_fields' => $this->systemFields(),
            'standard_fields' => [
                '_inherited_fields' => ['name' => ['originId' => 5]],
            ],
        ];
    }

    private function fullSource(): array
    {
        return [
            'system_fields' => $this->systemFields(),
            'standard_fields' => [
                '_inherited_fields' => ['name' => ['originId' => 5]],
                'name' => 'Test object',
                'description' => ['en' => 'A long description'],
            ],
        ];
    }

    /**
     * The search helper writes the children count of the hit into the system fields.
     */
    private function expectedFullSearchIndexData(): array
    {
        $data = $this->fullSource();
        $data['system_fields']['hasChildren'] = true;

        return $data;
    }

    private function systemFields(): array
    {
        return [
            'id' => self::OBJECT_ID,
            'classId' => 'CAR',
            'className' => 'Car',
            'parentId' => 1,
            'type' => 'object',
            'published' => true,
            'key' => 'test-object',
            'index' => 0,
            'path' => '/test/',
            'fullPath' => '/test/test-object',
            'userOwner' => 1,
            'userModification' => 1,
            'creationDate' => '2026-07-21T10:00:00+0000',
            'modificationDate' => '2026-07-21T10:00:00+0000',
            'hasWorkflowWithPermissions' => true,
            'hasChildren' => false,
        ];
    }
}
