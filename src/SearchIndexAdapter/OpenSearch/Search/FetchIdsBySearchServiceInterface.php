<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;


interface FetchIdsBySearchServiceInterface
{
    public function fetchAllIds(OpenSearchSearchInterface $search, string $indexName, bool $sortById = true): array;
}