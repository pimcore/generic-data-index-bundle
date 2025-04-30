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

interface FileSizeAggregationServiceInterface
{
    /**
     * Returns the sum of the file sizes of all assets that match the given search criteria in bytes.
     */
    public function getFileSizeSum(AssetSearch $assetSearch): int;
}
