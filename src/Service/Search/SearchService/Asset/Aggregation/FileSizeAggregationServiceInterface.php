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

interface FileSizeAggregationServiceInterface
{
    /**
     * Returns the sum of the file sizes of all assets that match the given search criteria in bytes.
     */
    public function getFileSizeSum(AssetSearch $assetSearch): int;
}
