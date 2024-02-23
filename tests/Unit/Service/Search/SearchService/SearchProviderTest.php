<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchService;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProvider;

/**
 * @internal
 */
final class SearchProviderTest extends Unit
{
    public function testCreateAssetSearch():void
    {
        $searchProvider = new SearchProvider();
        $assetSearch = $searchProvider->createAssetSearch();

        $this->assertInstanceOf(AssetSearch::class, $assetSearch);
    }
}