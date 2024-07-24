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
