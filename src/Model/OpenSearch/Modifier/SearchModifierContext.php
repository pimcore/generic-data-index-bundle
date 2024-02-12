<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\SearchService\SearchServiceInterface;

class SearchModifierContext implements SearchModifierContextInterface
{
    public function __construct(
        private readonly Search $search,
        private readonly SearchServiceInterface $searchService,
    )
    {
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    public function getSearchService(): SearchServiceInterface
    {
        return $this->searchService;
    }
}