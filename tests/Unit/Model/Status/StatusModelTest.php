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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Status;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Status\ClientConfiguration;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Status\QueueStatus;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 */
final class StatusModelTest extends Unit
{
    public function testQueueStatusTotalIsPendingPlusDispatched(): void
    {
        $status = new QueueStatus(pending: 2, dispatched: 3, oldestPendingOperationTime: 10);

        $this->assertSame(5, $status->getTotal());
    }

    public function testQueueStatusRejectsNegativeCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(pending: -1, dispatched: 0, oldestPendingOperationTime: null);
    }

    public function testQueueStatusRequiresOldestTimeExactlyWhenEntriesArePending(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(pending: 0, dispatched: 1, oldestPendingOperationTime: 10);
    }

    public function testQueueStatusRequiresOldestTimeWhenEntriesArePending(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(pending: 1, dispatched: 0, oldestPendingOperationTime: null);
    }

    /**
     * Data contract: the status API exposes counts, a timestamp and configuration names only, never hosts,
     * credentials or paths. A new getter has to be added here deliberately.
     */
    public function testStatusModelsExposeOnlyTheDocumentedFields(): void
    {
        $this->assertSame(
            ['getTotal', 'getPending', 'getDispatched', 'getOldestPendingOperationTime'],
            $this->publicGetters(QueueStatus::class)
        );
        $this->assertSame(
            ['getClientType', 'getClientName', 'getIndexPrefix'],
            $this->publicGetters(ClientConfiguration::class)
        );
    }

    public function testClientConfigurationReportsValuesAsConfiguredEvenWhenEmpty(): void
    {
        $configuration = new ClientConfiguration(clientType: '', clientName: '', indexPrefix: '');

        $this->assertSame('', $configuration->getClientType());
        $this->assertSame('', $configuration->getClientName());
        $this->assertSame('', $configuration->getIndexPrefix());
    }

    /**
     * @param class-string $className
     *
     * @return string[]
     */
    private function publicGetters(string $className): array
    {
        $methods = (new ReflectionClass($className))->getMethods(ReflectionMethod::IS_PUBLIC);

        return array_values(array_filter(
            array_map(static fn (ReflectionMethod $method): string => $method->getName(), $methods),
            static fn (string $name): bool => $name !== '__construct'
        ));
    }
}
