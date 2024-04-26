<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

final readonly class FetchIdsBySearchService implements FetchIdsBySearchServiceInterface
{
    public function __construct(
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private SearchIndexServiceInterface $searchIndexService,
    )
    {
    }

    public function fetchAllIds(OpenSearchSearchInterface $search, string $indexName, bool $sortById = true): array
    {
        if ($sortById) {
            $search->setSortList(new FieldSortList([new FieldSort(SystemField::ID->getPath())]));
        }

        if ($search->getSortList()->isEmpty()) {
            throw new InvalidArgumentException('Search must have a sort defined to be able to fetch all ids');
        }

        return $this->doFetchIds($search, $indexName);
    }

    private function doFetchIds(OpenSearchSearchInterface $search, string $indexName, ?array $searchAfter = null): array
    {
        $search->setFrom(0);
        $search->setSize($this->getPageSize());
        $search->setSource(false);
        $search->setSearchAfter($searchAfter);
        $searchResult = $this->searchIndexService->search($search, $indexName);
        $ids = $searchResult->getIds();

        $lastHit = $searchResult->getLastHit();
        if ($lastHit && (count($ids) === $this->getPageSize())) {
            return array_merge($ids, $this->doFetchIds($search, $indexName, $lastHit->getSort()));
        }
        return $ids;
    }

    private function getPageSize(): int
    {
        $maxResultWindow = $this->searchIndexConfigService->getIndexSettings()['max_result_window'];

        return min($maxResultWindow, 10000);
    }
}