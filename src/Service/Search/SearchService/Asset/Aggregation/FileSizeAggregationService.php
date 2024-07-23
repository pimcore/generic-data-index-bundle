<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\FileSizeSumAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;

/**
 * @internal
 */
final readonly class FileSizeAggregationService implements FileSizeAggregationServiceInterface
{
    public function __construct(
        private AssetSearchServiceInterface $assetSearchService,
    )
    {
    }

    public function getFileSizeSum(AssetSearch $assetSearch): int
    {
        $aggregation = new FileSizeSumAggregation('fileSizeSum');
        $assetSearch
            ->addModifier($aggregation)
            ->setAggregationsOnly(true)
        ;

        $result = $this->assetSearchService->search($assetSearch);

        $sum = $result->getAggregation($aggregation->getAggregationName())?->getAggregationResult()['value'] ?? 0;
        return (int) $sum;
    }
}