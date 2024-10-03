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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ElementSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\SearchResult\ElementSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final readonly class ElementSearchService implements ElementSearchServiceInterface
{
    public function __construct(
        private GlobalIndexAliasServiceInterface $globalIndexAliasService,
        private PaginationInfoServiceInterface $paginationInfoService,
        private ElementSearchHelperInterface $searchHelper,
        private DataObjectSearchServiceInterface $dataObjectSearchService,
        private AssetSearchServiceInterface $assetSearchService,
        private DocumentSearchServiceInterface $documentSearchService,
    ) {
    }

    public function search(
        SearchInterface $elementSearch,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): ElementSearchResult {
        $elementSearch = $this->searchHelper->addSearchRestrictions($elementSearch, $permissionType);

        $searchResult = $this->searchHelper->performSearch(
            $elementSearch,
            $this->globalIndexAliasService->getElementSearchAliasName()
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
            throw new ElementSearchException($e->getMessage(), 0, $e);
        }
    }

    public function byId(ElementType $elementType, int $id, ?User $user = null): ?ElementSearchResultItemInterface
    {
        try {
            return match ($elementType) {
                ElementType::DOCUMENT => $this->documentSearchService->byId($id, $user),
                ElementType::ASSET => $this->assetSearchService->byId($id, $user),
                ElementType::DATA_OBJECT => $this->dataObjectSearchService->byId($id, $user),
            };
        } catch (Exception $e) {
            throw new ElementSearchException($e->getMessage(), 0, $e);
        }

    }
}
