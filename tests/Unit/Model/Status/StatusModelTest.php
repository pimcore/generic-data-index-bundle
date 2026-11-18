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
use Pimcore\Bundle\GenericDataIndexBundle\Service\Status\IndexQueueStatusService;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 */
final class StatusModelTest extends Unit
{
    public function testQueueStatusTotalIsPendingPlusDispatched(): void
    {
        $status = new QueueStatus(
            pending: 2,
            dispatched: 3,
            oldestPendingOperationTime: 10,
            oldestDispatchTime: 5,
        );

        $this->assertSame(5, $status->getTotal());
    }

    public function testQueueStatusRejectsNegativeCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(
            pending: -1,
            dispatched: 0,
            oldestPendingOperationTime: null,
            oldestDispatchTime: null,
        );
    }

    public function testQueueStatusRequiresOldestTimeExactlyWhenEntriesArePending(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(
            pending: 0,
            dispatched: 1,
            oldestPendingOperationTime: 10,
            oldestDispatchTime: 10,
        );
    }

    public function testQueueStatusRequiresOldestDispatchedTimeExactlyWhenEntriesAreDispatched(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(
            pending: 1,
            dispatched: 0,
            oldestPendingOperationTime: 10,
            oldestDispatchTime: 10,
        );
    }

    public function testQueueStatusRequiresOldestDispatchedTimeWhenEntriesAreDispatched(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(
            pending: 0,
            dispatched: 1,
            oldestPendingOperationTime: null,
            oldestDispatchTime: null,
        );
    }

    public function testQueueStatusRequiresOldestTimeWhenEntriesArePending(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueueStatus(
            pending: 1,
            dispatched: 0,
            oldestPendingOperationTime: null,
            oldestDispatchTime: null,
        );
    }

    /**
     * The status reader exposes counts, a timestamp and configuration names only, never hosts, credentials or
     * paths. A new getter has to be added here deliberately.
     */
    public function testStatusModelsExposeOnlyTheDocumentedFields(): void
    {
        $this->assertSame(
            [
                'getDispatched',
                'getOldestDispatchTime',
                'getOldestPendingOperationTime',
                'getPending',
                'getTotal',
            ],
            $this->publicGetters(QueueStatus::class)
        );
        $this->assertSame(
            ['getClientName', 'getClientType', 'getIndexPrefix'],
            $this->publicGetters(ClientConfiguration::class)
        );
    }

    public function testReaderClassesAreInternal(): void
    {
        foreach ([QueueStatus::class, ClientConfiguration::class, IndexQueueStatusService::class] as $className) {
            $this->assertStringContainsString(
                '@internal',
                (string) (new ReflectionClass($className))->getDocComment(),
                $className . ' must not become public API.'
            );
        }
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
     * @return list<string> sorted by name, because the order of ReflectionClass::getMethods() is not guaranteed
     */
    private function publicGetters(string $className): array
    {
        $methods = (new ReflectionClass($className))->getMethods(ReflectionMethod::IS_PUBLIC);

        $getters = array_values(array_filter(
            array_map(static fn (ReflectionMethod $method): string => $method->getName(), $methods),
            static fn (string $name): bool => $name !== '__construct'
        ));
        sort($getters);

        return $getters;
    }
}
