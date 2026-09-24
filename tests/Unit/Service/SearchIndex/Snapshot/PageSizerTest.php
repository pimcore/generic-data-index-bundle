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
    private const BUDGET = 16 * 1024 * 1024;

    public function testFirstPageRequestsASingleDocument(): void
    {
        // Nothing is known about document size yet, so the first page must not be able to
        // exceed the budget at all: one document is the smallest possible exposure.
        $this->assertSame(1, (new PageSizer(1000, self::BUDGET))->nextPageSize());
    }

    public function testSmallDocumentsFillUpToTheConfiguredMaximum(): void
    {
        $sizer = new PageSizer(1000, self::BUDGET);
        $sizer->recordPage(1, 2048);

        $this->assertSame(1000, $sizer->nextPageSize(), 'the configured maximum stays the ceiling');
    }

    public function testLargeDocumentsShrinkThePageToTheByteBudget(): void
    {
        $sizer = new PageSizer(1000, self::BUDGET);
        $sizer->recordPage(50, 50 * 200 * 1024);

        // 16 MiB / 200 KiB = 81 (integer division)
        $this->assertSame(81, $sizer->nextPageSize());
    }

    public function testARunOfLargerDocumentsShrinksTheNextPageImmediately(): void
    {
        $sizer = new PageSizer(1000, self::BUDGET);
        $sizer->recordPage(1, 2048);
        $sizer->recordPage(1000, 1000 * 2048);
        $this->assertSame(1000, $sizer->nextPageSize());

        // documents suddenly get 100x bigger: the cumulative average still says ~100 KiB
        // (which would allow 162 per page), the most recent page says 200 KiB
        $sizer->recordPage(1000, 1000 * 200 * 1024);

        $this->assertSame(81, $sizer->nextPageSize(), 'the most recent page decides, not the lagging average');
    }

    public function testASingleOutlierDoesNotCollapseThePage(): void
    {
        $sizer = new PageSizer(1000, self::BUDGET);
        // one 8 MiB document among a thousand small ones barely moves either estimate
        $sizer->recordPage(1000, 8 * 1024 * 1024 + 1000 * 2048);

        $this->assertGreaterThan(1, $sizer->nextPageSize());
    }

    public function testPageNeverDropsBelowOneDocument(): void
    {
        $sizer = new PageSizer(1000, 1024);
        $sizer->recordPage(3, 3 * 5 * 1024 * 1024);

        $this->assertSame(1, $sizer->nextPageSize());
    }

    public function testPageNeverExceedsTheConfiguredMaximum(): void
    {
        $sizer = new PageSizer(10, self::BUDGET);
        $sizer->recordPage(1, 1);

        $this->assertSame(10, $sizer->nextPageSize());
    }

    public function testAnEmptyPageLeavesTheEstimateUntouched(): void
    {
        $sizer = new PageSizer(1000, self::BUDGET);
        $sizer->recordPage(50, 50 * 200 * 1024);
        $sizer->recordPage(0, 0);

        $this->assertSame(81, $sizer->nextPageSize(), 'an empty page carries no size information');
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
