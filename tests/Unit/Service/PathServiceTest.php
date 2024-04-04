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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\PathService;
use ValueError;

/**
 * @internal
 */
final class PathServiceTest extends Unit
{
    public function testIsSubPath(): void
    {
        $pathService = new PathService();

        $this->assertTrue($pathService->isSubPath('/foo/bar/baz', '/foo/bar'));
        $this->assertTrue($pathService->isSubPath('/foo', '/'));
        $this->assertFalse($pathService->isSubPath('/', '/'));
        $this->assertFalse($pathService->isSubPath('/', '/foo'));
        $this->assertFalse($pathService->isSubPath('/foo', '/foo/bar'));
        $this->assertFalse($pathService->isSubPath('/foo', '/asdf'));
    }

    public function testContainsSubPath(): void
    {
        $pathService = new PathService();
        $this->assertTrue($pathService->containsSubPath('/foo/bar', ['/foo/bar/baz']));
        $this->assertTrue($pathService->containsSubPath('/foo/bar', ['/asdf','/foo/bar/baz']));
        $this->assertTrue($pathService->containsSubPath('/foo/bar', ['/asdf','/foo/bar/baz/asdf']));
        $this->assertFalse($pathService->containsSubPath('/foo/bar', ['/foo/bar']));
        $this->assertFalse($pathService->containsSubPath('/foo/bar', ['/asdf']));
    }

    public function testGetContainedSubPaths(): void
    {
        $pathService = new PathService();
        $this->assertEquals(
            ['/foo/bar/baz'],
            $pathService->getContainedSubPaths('/foo/bar', ['/foo/bar/baz'])
        );
        $this->assertEquals(
            ['/foo/bar/baz','/foo/bar/baz/asdf'],
            $pathService->getContainedSubPaths('/foo/bar', ['/foo/bar/baz','/foo/bar/baz/asdf'])
        );
        $this->assertEquals(
            [],
            $pathService->getContainedSubPaths('/foo/bar', ['/asdf'])
        );
        $this->assertEquals(
            ['/foo/bar/baz'],
            $pathService->getContainedSubPaths('/foo/bar', ['/asdf','/foo/bar/baz'])
        );
    }

    public function testRemoveSubPaths(): void
    {
        $pathService = new PathService();
        $this->assertEquals(
            ['/foo/bar'],
            $pathService->removeSubPaths(['/foo/bar/baz','/foo/bar'],)
        );
        $this->assertEquals(
            ['/foo/bar'],
            $pathService->removeSubPaths(['/foo/bar/baz','/foo/bar','/foo/bar/baz/asdf'])
        );
        $this->assertEquals(
            ['/asdf','/foo/bar'],
            $pathService->removeSubPaths(['/foo/bar','/asdf'])
        );
    }

    public function testCalculateLongestPathLevel(): void
    {
        $pathService = new PathService();
        $this->assertEquals(
            3,
            $pathService->calculateLongestPathLevel(['/foo/bar/baz','/foo/bar','/foo'])
        );
        $this->assertEquals(
            1,
            $pathService->calculateLongestPathLevel(['/foo'])
        );
        $this->assertEquals(
            0,
            $pathService->calculateLongestPathLevel([])
        );
        $this->assertEquals(
            0,
            $pathService->calculateLongestPathLevel(['/'])
        );
    }

    public function testAppendSlashes(): void
    {
        $pathService = new PathService();
        $this->assertEquals(
            ['/foo/bar/','/foo/bar/baz/'],
            $pathService->appendSlashes(['/foo/bar','/foo/bar/baz'])
        );
        $this->assertEquals(
            ['/foo/bar/','/foo/bar/baz/'],
            $pathService->appendSlashes(['/foo/bar/','/foo/bar/baz/'])
        );
        $this->assertEquals(
            ['/'],
            $pathService->appendSlashes(['/'])
        );
        $this->assertEquals(
            [],
            $pathService->appendSlashes([])
        );
    }

    public function testGetAllParentPaths(): void
    {
        $pathService = new PathService();

        $this->assertEquals(
            [
                '/',
                '/foo',
                '/foo/bar',
                '/foo/bar/baz',
            ],
            $pathService->getAllParentPaths(['/foo/bar/baz/qux'])
        );

        $this->assertEquals(
            [],
            $pathService->getAllParentPaths(['/'])
        );

        $this->assertEquals(
            [
                '/',
                '/foo',
                '/foo/bar',
            ],
            $pathService->getAllParentPaths([
                '/foo/bar/baz/qux',
                '/foo/bar/baz',
            ])
        );

        $this->expectException(ValueError::class);
        $pathService->getAllParentPaths(['asdf/asdf']);
    }
}
