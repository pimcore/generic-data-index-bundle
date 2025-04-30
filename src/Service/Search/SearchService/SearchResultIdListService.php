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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\FetchIdsServiceInterface;

/**
 * @internal
 */
final readonly class SearchResultIdListService implements SearchResultIdListServiceInterface
{
    public function __construct(
        private TransformToAdapterSearchServiceInterface $transformToAdapterSearchService,
        private IndexNameResolverInterface $indexNameResolver,
        private FetchIdsServiceInterface $fetchIdsService,
    ) {
    }

    public function getAllIds(SearchInterface $search): array
    {
        $this->validateSearch($search);

        return $this->fetchIdsService->fetchAllIds(
            $this->transformToAdapterSearchService->transform($search, true),
            $this->indexNameResolver->resolveIndexName($search)
        );
    }

    public function getIdsForCurrentPage(SearchInterface $search): array
    {
        $this->validateSearch($search);

        return $this->fetchIdsService->fetchIdsForCurrentPage(
            $this->transformToAdapterSearchService->transform($search),
            $this->indexNameResolver->resolveIndexName($search)
        );
    }

    private function validateSearch(SearchInterface $search): void
    {
        if ($search instanceof AssetSearch
            || $search instanceof DataObjectSearch
            || $search instanceof DocumentSearch
        ) {
            return;
        }

        if ($search instanceof ElementSearch) {
            throw new InvalidArgumentException(
                'ElementSearch is not supported by SearchResultIdListService as it might return different element types. Use ElementSearchService instead.'
            );
        }

        throw new InvalidArgumentException(
            'SearchInterface must be an instance of AssetSearch, DataObjectSearch or DocumentSearch'
        );
    }
}
