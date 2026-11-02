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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchResultItem\LazyLoading;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\FullSourceLazyLoadingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class FullSourceLazyLoadingHandlerTest extends Unit
{
    /**
     * A data object that is no longer found is logged with the injected logger, without failing on an
     * uninitialized logger.
     */
    public function testMissingDataObjectIsLogged(): void
    {
        $dataObjectSearchService = $this->createMock(DataObjectSearchServiceInterface::class);
        $dataObjectSearchService->method('search')->willReturn(
            new DataObjectSearchResult(
                items: [],
                pagination: new PaginationInfo(totalItems: 0, page: 1, pageSize: 1, totalPages: 0),
            )
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'Data object not found in search index',
            ['id' => 42]
        );

        $handler = $this->handler($dataObjectSearchService, $logger, PermissionTypes::LIST);
        $handler->lazyLoad((new DataObjectSearchResultItem())->setId(42));
    }

    /**
     * apply() switches the user, but keeps the permission type of the originating search.
     */
    public function testApplyKeepsThePermissionType(): void
    {
        $user = (new User())->setId(3);
        $dataObjectSearchService = $this->createMock(DataObjectSearchServiceInterface::class);
        $dataObjectSearchService
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(
                function (DataObjectSearchInterface $search, PermissionTypes $permissionType) use (
                    $user
                ): DataObjectSearchResult {
                    $this->assertSame(PermissionTypes::VIEW, $permissionType);
                    $this->assertSame($user, $search->getUser());

                    return new DataObjectSearchResult(
                        items: [
                            (new DataObjectSearchResultItem())
                                ->setHasChildren(false)
                                ->setHasWorkflowWithPermissions(false)
                                ->setSearchIndexData(['full' => true]),
                        ],
                        pagination: new PaginationInfo(totalItems: 1, page: 1, pageSize: 1, totalPages: 1),
                    );
                }
            );

        $handler = $this->handler(
            $dataObjectSearchService,
            $this->createMock(LoggerInterface::class),
            PermissionTypes::VIEW
        );
        $item = $handler->apply((new DataObjectSearchResultItem())->setId(42), $user);

        $this->assertSame(['full' => true], $item->getSearchIndexData());
    }

    private function handler(
        DataObjectSearchServiceInterface $dataObjectSearchService,
        LoggerInterface $logger,
        PermissionTypes $permissionType
    ): FullSourceLazyLoadingHandler {
        return new FullSourceLazyLoadingHandler(
            dataObjectSearchService: $dataObjectSearchService,
            logger: $logger,
            permissionType: $permissionType,
            searchProvider: $this->createConfiguredMock(SearchProviderInterface::class, [
                'createDataObjectSearch' => new DataObjectSearch(),
            ]),
        );
    }
}
