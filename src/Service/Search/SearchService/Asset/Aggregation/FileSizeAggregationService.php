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
    ) {
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
