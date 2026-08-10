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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexQueue;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolver;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessageService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final class QueueMessageServiceTest extends Unit
{
    private QueueMessageService $queueMessageService;

    public function _before(): void
    {
        $this->queueMessageService = new QueueMessageService(
            $this->getEmptyQueueRepository(),
            $this->makeEmpty(MessageBusInterface::class),
            new CalculatedFieldsIndexModeResolver(),
        );
    }

    public function testGetMaxBatchSizeWithOneWorker(): void
    {
        $this->assertSame(
            40,
            $this->queueMessageService->getMaxBatchSize(
                100,
                1,
                10,
                40
            )
        );
    }

    public function testGetMaxBatchSizeWithMultipleWorkers(): void
    {
        $this->assertSame(
            50,
            $this->queueMessageService->getMaxBatchSize(
                250,
                5,
                5,
                400
            )
        );
    }

    public function testGetMaxBatchSizeWithOneWorkerAndFewItems(): void
    {
        $this->assertSame(
            50,
            $this->queueMessageService->getMaxBatchSize(
                20,
                1,
                10,
                50
            )
        );
    }

    public function testGetMaxBatchSizeWithMultipleWorkersAndFewItems(): void
    {
        $this->assertSame(
            10,
            $this->queueMessageService->getMaxBatchSize(
                20,
                2,
                5,
                500
            )
        );
    }

    private function getEmptyQueueRepository(): IndexQueueRepository
    {
        return new IndexQueueRepository(
            $this->makeEmpty(EntityManagerInterface::class),
            $this->makeEmpty(TimeServiceInterface::class),
            $this->makeEmpty(Connection::class),
            $this->makeEmpty(DenormalizerInterface::class)
        );
    }
}
