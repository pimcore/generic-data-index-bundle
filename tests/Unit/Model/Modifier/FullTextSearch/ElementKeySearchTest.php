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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\FullTextSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\ElementKeySearch;

class ElementKeySearchTest extends Unit
{
    public function testGetSearchTermWithNull()
    {
        $filter = new ElementKeySearch(null);
        $this->assertNull($filter->getSearchTerm());
    }

    public function testGetSearchTermWithString()
    {
        $filter = new ElementKeySearch('my-filter');
        $this->assertSame('my-filter', $filter->getSearchTerm());
    }
}