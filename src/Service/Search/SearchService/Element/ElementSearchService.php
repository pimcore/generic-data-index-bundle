<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ElementSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\SearchResult\ElementSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;

/**
 * @internal
 */
final readonly class ElementSearchService implements ElementSearchServiceInterface
{
    public function __construct(
        private GlobalIndexAliasServiceInterface $globalIndexAliasService,
        private PaginationInfoServiceInterface $paginationInfoService,
        private SearchHelper $searchHelper,
        private SearchProviderInterface $searchProvider
    ) {
    }

    public function search(SearchInterface $elementSearch): ElementSearchResult
    {
        /* $documentSearch = $this->searchHelper->addSearchRestrictions(
             search: $elementSearch,
             userPermission: UserPermissionTypes::DOCUMENTS->value,
             workspaceType: DocumentWorkspace::WORKSPACE_TYPE
         );*/

        $searchResult = $this->searchHelper->performSearch(
            search: $elementSearch,
            indexName: $this->globalIndexAliasService->getElementSearchAliasName()
        );

        try {
            return new ElementSearchResult(
                items: $this->searchHelper->hydrateSearchResultHits(
                    $searchResult,
                    [],
                    $elementSearch->getUser()
                ),
                pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                    searchResult: $searchResult,
                    page: $elementSearch->getPage(),
                    pageSize: $elementSearch->getPageSize()
                ),
                aggregations:  $searchResult->getAggregations(),
            );
        } catch (Exception $e) {
            throw new ElementSearchException($e->getMessage());
        }
    }
}
