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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\FullTextSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\ElementKeySearch;

/**
 * @internal
 */
final class ElementKeySearchTest extends Unit
{
    public function testGetSearchTermWithNull(): void
    {
        $filter = new ElementKeySearch(null);
        $this->assertNull($filter->getSearchTerm());
    }

    public function testGetSearchTermWithString(): void
    {
        $filter = new ElementKeySearch('my-filter');
        $this->assertSame('my-filter', $filter->getSearchTerm());
    }
}
