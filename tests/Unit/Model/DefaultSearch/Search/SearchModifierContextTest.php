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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\DefaultSearch\Search;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

/**
 * @internal
 */
final class SearchModifierContextTest extends Unit
{
    public function testGetSearch(): void
    {
        $searchMock = $this->makeEmpty(DefaultSearchInterface::class);
        $assetSearchMock = $this->makeEmpty(SearchInterface::class);
        $searchModifierContext = new SearchModifierContext($searchMock, $assetSearchMock);

        $this->assertSame($searchMock, $searchModifierContext->getSearch());
        $this->assertSame($assetSearchMock, $searchModifierContext->getOriginalSearch());
    }
}
