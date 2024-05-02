<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Search;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;

/**
 * @internal
 */
final class SearchModifierContextTest extends Unit
{
    public function testGetSearch(): void
    {
        $searchMock = $this->makeEmpty(OpenSearchSearchInterface::class);
        $assetSearchMock = $this->makeEmpty(AssetSearch::class);
        $searchModifierContext = new SearchModifierContext($searchMock, $assetSearchMock);

        $this->assertSame($searchMock, $searchModifierContext->getSearch());
        $this->assertSame($assetSearchMock, $searchModifierContext->getOriginalSearch());
    }
}
