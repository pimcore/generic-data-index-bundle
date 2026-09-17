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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ClassCompatibility;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\CompatibilityReport;

final class CompatibilityReportTest extends Unit
{
    public function testIsCompatibleIsTrueWhenNoClassIsIncompatible(): void
    {
        $report = new CompatibilityReport([
            new ClassCompatibility('12', 'simple', ClassCompatibilityStatus::COMPATIBLE, 111, 111, 111),
            new ClassCompatibility('13', 'other', ClassCompatibilityStatus::STALE_STORE, 222, 999, 222),
        ], []);

        $this->assertTrue($report->isCompatible());
        $this->assertSame([], $report->incompatibleClassIds());
    }

    public function testIncompatibleClassIdsListsOnlyIncompatibleOnes(): void
    {
        // a numeric-string class id, exactly as it arrives once it has passed through a
        // manifest's class_mapping_checksums array (PHP coerces numeric string keys to int,
        // so production code must cast back to string before reaching here)
        $report = new CompatibilityReport([
            new ClassCompatibility('12', 'simple', ClassCompatibilityStatus::INCOMPATIBLE, 111, 222, 333),
            new ClassCompatibility('13', 'other', ClassCompatibilityStatus::COMPATIBLE, 222, 222, 222),
        ], []);

        $this->assertFalse($report->isCompatible());
        $this->assertSame(['12'], $report->incompatibleClassIds());
    }

    public function testStatusOfFindsAStringClassId(): void
    {
        $report = new CompatibilityReport([
            new ClassCompatibility('12', 'simple', ClassCompatibilityStatus::INCOMPATIBLE, 111, 222, 333),
        ], []);

        $this->assertSame(ClassCompatibilityStatus::INCOMPATIBLE, $report->statusOf('12'));
        $this->assertNull($report->statusOf('does-not-exist'));
    }
}
