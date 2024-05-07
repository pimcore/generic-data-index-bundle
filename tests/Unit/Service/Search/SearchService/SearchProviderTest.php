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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchService;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProvider;

/**
 * @internal
 */
final class SearchProviderTest extends Unit
{
    public function testCreateAssetSearch(): void
    {
        $searchProvider = new SearchProvider();
        $assetSearch = $searchProvider->createAssetSearch();

        $this->assertInstanceOf(AssetSearch::class, $assetSearch);
    }

    public function testCreateDataObjectSearch(): void
    {
        $searchProvider = new SearchProvider();
        $assetSearch = $searchProvider->createDataObjectSearch();

        $this->assertInstanceOf(DataObjectSearch::class, $assetSearch);
    }
}
