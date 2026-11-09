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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Diagnostics;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Diagnostics\IndexHealthCheck;
use Pimcore\Bundle\GenericDataIndexBundle\Diagnostics\IndexQueueBacklogCheck;
use Pimcore\Contracts\Diagnostics\DataClass;
use Pimcore\Contracts\Diagnostics\DataContract;
use ReflectionClass;

/**
 * Every check of this bundle must stay inside the data classes the diagnostics overview runs.
 *
 * The list below is duplicated on purpose (this bundle must not import the diagnostics MCP bundle). It mirrors
 * `DiagnosticsOverviewTool::RETURNS` in pimcore/enterprise-monorepo, `bundles/DiagnosticsMcpBundle`. A check that
 * declares a class outside this list is marked `contract_out_of_scope` there: it is listed but never run, and it
 * contributes no findings, without any error.
 *
 * @internal
 */
final class DataContractScopeTest extends Unit
{
    // Mirrors DiagnosticsOverviewTool::RETURNS in pimcore/enterprise-monorepo, bundles/DiagnosticsMcpBundle.
    // A data class outside this list drops the check out of the overview silently (contract_out_of_scope).
    private const array OVERVIEW_RETURNS = [
        DataClass::Version,
        DataClass::Configuration,
        DataClass::Metric,
        DataClass::Identifier,
    ];

    public function testChecksReturnOnlyDataClassesTheOverviewRuns(): void
    {
        foreach ([IndexQueueBacklogCheck::class, IndexHealthCheck::class] as $checkClass) {
            $attributes = (new ReflectionClass($checkClass))->getAttributes(DataContract::class);
            $this->assertCount(1, $attributes, $checkClass . ' declares no DataContract.');

            $returns = $attributes[0]->newInstance()->returns;
            $outOfScope = array_values(array_filter(
                $returns,
                static fn (DataClass $dataClass): bool => !in_array($dataClass, self::OVERVIEW_RETURNS, true),
            ));

            $this->assertSame(
                [],
                $outOfScope,
                sprintf('%s returns data classes outside the diagnostics overview scope.', $checkClass)
            );
        }
    }
}
