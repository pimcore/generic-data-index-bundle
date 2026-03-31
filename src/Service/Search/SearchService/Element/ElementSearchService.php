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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ElementSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\SearchResult\ElementSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
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
        ElementSearchInterface $elementSearch,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): ElementSearchResult {
        $search = $this->searchHelper->addSearchRestrictions($elementSearch, $permissionType);

        $searchResult = $this->searchHelper->performSearch(
            $search,
            $this->globalIndexAliasService->getElementSearchAliasName()
        );

        try {
            return new ElementSearchResult(
                items: $this->searchHelper->hydrateSearchResultHits(
                    $searchResult,
                    [],
                    $search->getUser()
                ),
                pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                    searchResult: $searchResult,
                    page: $search->getPage(),
                    pageSize: $search->getPageSize()
                ),
                aggregations:  $searchResult->getAggregations(),
            );
        } catch (Exception $e) {
            throw new ElementSearchException($e->getMessage(), 0, $e);
        }
    }

    public function byId(
        ElementType $elementType,
        int $id,
        ?User $user = null,
        bool $forceReload = false
    ): ?ElementSearchResultItemInterface {
        try {
            return match ($elementType) {
                ElementType::DOCUMENT => $this->documentSearchService->byId($id, $user, $forceReload),
                ElementType::ASSET => $this->assetSearchService->byId($id, $user, $forceReload),
                ElementType::DATA_OBJECT => $this->dataObjectSearchService->byId($id, $user, $forceReload),
            };
        } catch (Exception $e) {
            throw new ElementSearchException($e->getMessage(), 0, $e);
        }

    }
}
