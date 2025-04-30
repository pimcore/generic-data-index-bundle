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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\DefaultSearch\Search\Asset;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

/**
 * @internal
 */
final class AssetSearchTest extends Unit
{
    public function testAssetSearch()
    {
        $searchModifierMock1 = $this->makeEmpty(SearchModifierInterface::class);
        $searchModifierMock2 = $this->makeEmpty(SearchModifierInterface::class);
        $assetSearch = new AssetSearch();
        $assetSearch->addModifier($searchModifierMock1);
        $assetSearch->addModifier($searchModifierMock2);

        $this->assertCount(2, $assetSearch->getModifiers());
        $this->assertSame([
            $searchModifierMock1,
            $searchModifierMock2,
        ], $assetSearch->getModifiers());
    }
}
