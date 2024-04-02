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
