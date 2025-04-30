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
