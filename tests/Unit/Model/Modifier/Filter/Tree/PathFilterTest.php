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
