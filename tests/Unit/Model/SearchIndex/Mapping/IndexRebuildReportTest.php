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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\SearchIndex\Mapping;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexRebuild;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexRebuildReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;

/**
 * @internal
 */
final class IndexRebuildReportTest extends Unit
{
    /**
     * A class may be named like the asset or document index label; both results are reported.
     */
    public function testRebuildsWithTheSameLabelAreBothKept(): void
    {
        $report = new IndexRebuildReport([
            new IndexRebuild('assets', new MappingUpdateResult(['standard_fields.a'])),
            new IndexRebuild('assets', new MappingUpdateResult(recreated: true)),
        ]);

        $this->assertFalse($report->isEmpty());
        $this->assertCount(2, $report->getRebuilds());
        $this->assertSame('assets', $report->getRebuilds()[1]->getLabel());
        $this->assertTrue($report->getRebuilds()[1]->getResult()->isRecreated());
    }

    public function testEmptyReport(): void
    {
        $this->assertTrue((new IndexRebuildReport())->isEmpty());
    }
}
