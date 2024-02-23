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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Search\Traits;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Traits\PaginatedSearchTrait;

/**
 * @internal
 */
final class PaginatedSearchTraitTest extends Unit
{
    public function testSetPageWithNegativePageNumber(): void
    {
        $class = $this->getClassWithTrait();
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Page must be bigger then 0");
        $class->setPage(-42);
    }

    public function testSetPageWithZeroAsPageNumber(): void
    {
        $class = $this->getClassWithTrait();
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Page must be bigger then 0");
        $class->setPage(0);
    }

    public function testSetPageSizeWithNegativePageNumber(): void
    {
        $class = $this->getClassWithTrait();
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Page Size must be bigger then 0");
        $class->setPageSize(-42);
    }

    public function testSetPageSizeWithNegativePageSize(): void
    {
        $class = $this->getClassWithTrait();
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Page Size must be bigger then 0");
        $class->setPageSize(-50);
    }

    public function testSetPageSizeWithZeroAsPageSize(): void
    {
        $class = $this->getClassWithTrait();
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Page Size must be bigger then 0");
        $class->setPageSize(0);
    }

    public function testGetPage()
    {
        $class = $this->getClassWithTrait();
        $class->setPage(42);
        $this->assertSame(42, $class->getPage());
    }

    public function testGetPageSize()
    {
        $class = $this->getClassWithTrait();
        $class->setPageSize(42);
        $this->assertSame(42, $class->getPageSize());
    }

    private function getClassWithTrait(): object
    {
        return new class {
            use PaginatedSearchTrait;
        };
    }
}