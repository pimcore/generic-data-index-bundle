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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;

/**
 * Loads the full search index data of an item found by a search with the SystemFieldsOnlySource modifier.
 *
 * It searches the data object by id with the user and the permission type of the originating search, so an item
 * the user may only list gets its data too. byId() is not used: it requires the VIEW permission and serves items
 * from the runtime cache.
 *
 * @internal
 */
final readonly class FullSourceLazyLoadingHandler implements DataObjectLazyLoadingHandlerInterface
{
    public function __construct(
        private DataObjectSearchServiceInterface $dataObjectSearchService,
        private LoggerInterface $logger,
        private PermissionTypes $permissionType,
        private SearchProviderInterface $searchProvider,
        private ?User $user = null,
    ) {
    }

    public function lazyLoad(DataObjectSearchResultItem $item): void
    {
        $search = $this->searchProvider->createDataObjectSearch();
        $search->setPageSize(1);
        $search->addModifier(new IdFilter($item->getId()));
        if ($this->user) {
            $search->setUser($this->user);
        }

        $indexItem = $this->dataObjectSearchService->search($search, $this->permissionType)->getItems()[0] ?? null;
        if (!$indexItem) {
            $this->logger->warning('Data object not found in search index', ['id' => $item->getId()]);

            return;
        }

        $item
            ->setHasChildren($indexItem->isHasChildren())
            ->setSearchIndexData($indexItem->getSearchIndexData())
            ->setHasWorkflowWithPermissions($indexItem->isHasWorkflowWithPermissions());
    }

    public function apply(DataObjectSearchResultItem $item, ?User $user): DataObjectSearchResultItem
    {
        return $item->withLazyLoadingHandler(
            new FullSourceLazyLoadingHandler(
                dataObjectSearchService: $this->dataObjectSearchService,
                logger: $this->logger,
                permissionType: $this->permissionType,
                searchProvider: $this->searchProvider,
                user: $user,
            )
        );
    }
}
