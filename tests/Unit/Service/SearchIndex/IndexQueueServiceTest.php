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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexCompletionNotifierInterface;
use Pimcore\Model\Asset;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Which queue writes updateIndexQueue() does for the enqueueRelatedItems and enqueueDependentItems flags. The calls
 * are recorded instead of asserted through mock expectations, because updateIndexQueue() catches and logs every
 * exception: an expectation failure thrown during the call would be swallowed.
 *
 * @internal
 */
final class IndexQueueServiceTest extends Unit
{
    /** @var string[] */
    private array $calls = [];

    /** @var string[] */
    private array $loggedErrors = [];

    protected function _before(): void
    {
        $this->calls = [];
        $this->loggedErrors = [];
    }

    /**
     * PEES-1991: a dependency change reindexes the asset (its isReferenced flag) without its dependents. Enqueuing
     * every element that depends on the asset made each new relation to a widely used asset cost one queue write per
     * existing dependent.
     */
    public function testAsyncUpdateWithoutDependentItemsEnqueuesOnlyTheAsset(): void
    {
        $this->createService(transactionNestingLevel: 0)->updateIndexQueue(
            $this->createAsset(),
            IndexQueueOperation::UPDATE->value,
            processSynchronously: false,
            enqueueRelatedItems: false,
            enqueueDependentItems: false,
        );

        $this->assertSame(['enqueueRelatedItems:42:include', 'rewriteChildrenIndexPaths:42'], $this->calls);
        $this->assertSame([], $this->loggedErrors);
    }

    /**
     * An open transaction turns a synchronous update into a queued one; the dependents must stay out of it as well.
     */
    public function testSynchronousUpdateInsideATransactionWithoutDependentItemsEnqueuesOnlyTheAsset(): void
    {
        $this->createService(transactionNestingLevel: 1)->updateIndexQueue(
            $this->createAsset(),
            IndexQueueOperation::UPDATE->value,
            processSynchronously: true,
            enqueueRelatedItems: false,
            enqueueDependentItems: false,
        );

        $this->assertSame(['enqueueRelatedItems:42:include', 'rewriteChildrenIndexPaths:42'], $this->calls);
        $this->assertSame([], $this->loggedErrors);
    }

    public function testSynchronousUpdateWithoutRelatedItemsEnqueuesNothing(): void
    {
        $this->createService(transactionNestingLevel: 0)->updateIndexQueue(
            $this->createAsset(),
            IndexQueueOperation::UPDATE->value,
            processSynchronously: true,
            enqueueRelatedItems: false,
            enqueueDependentItems: false,
        );

        $this->assertSame(['updateIndexData:42', 'rewriteChildrenIndexPaths:42'], $this->calls);
        $this->assertSame([], $this->loggedErrors);
    }

    public function testAsyncAssetUpdateStillEnqueuesTheDependentsByDefault(): void
    {
        $this->createService(transactionNestingLevel: 0)->updateIndexQueue(
            $this->createAsset(),
            IndexQueueOperation::UPDATE->value,
            processSynchronously: false,
            enqueueRelatedItems: false,
        );

        $this->assertSame(
            ['enqueueRelatedItems:42:include', 'enqueueDependentItems:42', 'rewriteChildrenIndexPaths:42'],
            $this->calls
        );
        $this->assertSame([], $this->loggedErrors);
    }

    /**
     * Element deletes pass enqueueRelatedItems false when the element has no children (see the data object
     * subscriber); the elements referencing the deleted one must still be enqueued.
     */
    public function testAsyncDeleteWithoutRelatedItemsStillEnqueuesTheDependents(): void
    {
        $this->createService(transactionNestingLevel: 0)->updateIndexQueue(
            $this->createAsset(),
            IndexQueueOperation::DELETE->value,
            processSynchronously: false,
            enqueueRelatedItems: false,
        );

        $this->assertSame(
            ['enqueueRelatedItems:42:include', 'enqueueDependentItems:42', 'rewriteChildrenIndexPaths:42'],
            $this->calls
        );
        $this->assertSame([], $this->loggedErrors);
    }

    private function createService(int $transactionNestingLevel): IndexQueueService
    {
        $service = new IndexQueueService(
            $this->makeEmpty(IndexServiceInterface::class, [
                'updateIndexData' => function (Asset $asset): IndexService {
                    $this->calls[] = 'updateIndexData:' . $asset->getId();

                    // final, the return value is not used
                    return (new ReflectionClass(IndexService::class))->newInstanceWithoutConstructor();
                },
            ]),
            $this->makeEmpty(PathServiceInterface::class, [
                'rewriteChildrenIndexPaths' => function (Asset $asset): void {
                    $this->calls[] = 'rewriteChildrenIndexPaths:' . $asset->getId();
                },
            ]),
            $this->makeEmpty(BulkOperationServiceInterface::class),
            // final and unused on these paths
            (new ReflectionClass(IndexQueueRepository::class))->newInstanceWithoutConstructor(),
            $this->makeEmpty(EnqueueServiceInterface::class, [
                'enqueueRelatedItems' => function (Asset $asset, bool $includeElement): void {
                    $this->calls[] = 'enqueueRelatedItems:' . $asset->getId() . ($includeElement ? ':include' : '');
                },
                'enqueueDependentItems' => function (Asset $asset): void {
                    $this->calls[] = 'enqueueDependentItems:' . $asset->getId();
                },
            ]),
            $this->makeEmpty(ElementServiceInterface::class),
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(MessageBusInterface::class),
            $this->makeEmpty(Connection::class, ['getTransactionNestingLevel' => $transactionNestingLevel]),
            $this->makeEmpty(IndexCompletionNotifierInterface::class),
        );
        $service->setLogger($this->makeEmpty(LoggerInterface::class, [
            'error' => function (string $message, array $context = []): void {
                $this->loggedErrors[] = $message . ': ' . ($context['exception'] ?? null)?->getMessage();
            },
        ]));

        return $service;
    }

    private function createAsset(): Asset
    {
        $asset = new Asset();
        $asset->setId(42);

        return $asset;
    }
}
