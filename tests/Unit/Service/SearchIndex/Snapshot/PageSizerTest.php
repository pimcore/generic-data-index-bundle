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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\PageSizer;

final class PageSizerTest extends Unit
{
    public function testFirstPageIsABoundedProbe(): void
    {
        $sizer = new PageSizer(maxDocuments: 1000, byteBudget: 16 * 1024 * 1024);

        $this->assertSame(PageSizer::PROBE_SIZE, $sizer->nextPageSize(0, 0));
    }

    public function testProbeNeverExceedsTheConfiguredMaximum(): void
    {
        $sizer = new PageSizer(maxDocuments: 10, byteBudget: 16 * 1024 * 1024);

        $this->assertSame(10, $sizer->nextPageSize(0, 0));
    }

    public function testSmallDocumentsFillUpToTheConfiguredMaximum(): void
    {
        $sizer = new PageSizer(maxDocuments: 1000, byteBudget: 16 * 1024 * 1024);

        // 50 documents of 2 KB: the budget would allow 8192 per page, the maximum wins
        $this->assertSame(1000, $sizer->nextPageSize(50, 50 * 2048));
    }

    public function testLargeDocumentsShrinkThePageToTheByteBudget(): void
    {
        $sizer = new PageSizer(maxDocuments: 1000, byteBudget: 16 * 1024 * 1024);

        // 50 documents averaging 200 KB: 16 MiB / 200 KiB = 81 (integer division)
        $this->assertSame(81, $sizer->nextPageSize(50, 50 * 200 * 1024));
    }

    public function testPageNeverDropsBelowOneDocument(): void
    {
        $sizer = new PageSizer(maxDocuments: 1000, byteBudget: 1024);

        $this->assertSame(1, $sizer->nextPageSize(3, 3 * 5 * 1024 * 1024));
    }

    public function testRunningAverageKeepsAdapting(): void
    {
        $sizer = new PageSizer(maxDocuments: 1000, byteBudget: 1000);

        $this->assertSame(100, $sizer->nextPageSize(10, 100), 'avg 10 bytes -> 100 per page');
        $this->assertSame(5, $sizer->nextPageSize(110, 100 + 100 * 200), 'avg grew to ~182 bytes -> 5 per page');
    }

    public function testRejectsNonPositiveLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageSizer(maxDocuments: 0, byteBudget: 1);
    }

    public function testRejectsNonPositiveByteBudget(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageSizer(maxDocuments: 1, byteBudget: 0);
    }
}
