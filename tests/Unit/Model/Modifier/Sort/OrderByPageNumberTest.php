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
