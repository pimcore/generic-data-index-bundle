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
