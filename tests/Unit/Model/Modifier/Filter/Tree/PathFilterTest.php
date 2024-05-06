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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Filter\Tree;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\PathFilter;
use ValueError;

/**
 * @internal
 */
final class PathFilterTest extends Unit
{
    public function testPathFilterWithoutSlash(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Path must start with a slash.');
        new PathFilter('test');
    }

    public function testPathFilterWithMultipleSlashes(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Path must not contain consecutive slashes.');
        new PathFilter('/test//path');
    }

    public function testPathFilterGetters(): void
    {
        $filter = new PathFilter('/test/path');
        $this->assertSame('/test/path', $filter->getPath());
        $this->assertSame('/test/path', $filter->getPathWithoutTrailingSlash());
        $this->assertSame('/test/path/', $filter->getPathWithTrailingSlash());

        $filter = new PathFilter('/test/path/');
        $this->assertSame('/test/path/', $filter->getPath());
        $this->assertSame('/test/path', $filter->getPathWithoutTrailingSlash());
        $this->assertSame('/test/path/', $filter->getPathWithTrailingSlash());
    }
}
