<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\ValidationFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResult;

interface AssetSearchServiceInterface extends SearchServiceInterface
{
    /**
     * @throws ValidationFailedException
     */
    public function search(AssetSearch $assetSearch): AssetSearchResult;
}