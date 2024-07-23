<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;

interface FileSizeAggregationServiceInterface
{
    /**
     * Returns the sum of the file sizes of all assets that match the given search criteria in bytes.
     */
    public function getFileSizeSum(AssetSearch $assetSearch): int;
}