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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Sort;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByPageNumber;

/**
 * @internal
 */
final class OrderByPageNumberTest extends Unit
{
    public function testGetParameters(): void
    {
        $indexName = 'assets';
        $search = new AssetSearch();

        $filter = new OrderByPageNumber(
            $indexName,
            $search
        );

        $this->assertSame($search, $filter->getSearch());
        $this->assertSame($indexName, $filter->getIndexName());
    }
}
