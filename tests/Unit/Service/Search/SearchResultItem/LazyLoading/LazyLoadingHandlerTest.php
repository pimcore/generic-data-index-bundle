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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchResultItem\LazyLoading;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\AssetLazyLoadingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\DataObjectLazyLoadingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\DocumentLazyLoadingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Regression tests for pimcore/platform-version#742: a lazy load that finds nothing in the index
 * (element not indexed yet, index out of sync, no view permission) must log a warning and leave
 * the lazy-loaded getters with defined results instead of failing on uninitialized properties.
 * The failed lookup is done (and logged) only once per item, not once per getter call.
 *
 * @internal
 */
final class LazyLoadingHandlerTest extends Unit
{
    private const ELEMENT_ID = 42;

    public function testDataObjectItemNotFoundInIndexLogsWarningAndReturnsDefaults(): void
    {
        $searchService = $this->createMock(DataObjectSearchServiceInterface::class);
        $searchService->expects($this->once())->method('byId')->willReturn(null);

        $handler = new DataObjectLazyLoadingHandler($searchService);
        $handler->setLogger(
            $this->createWarningExpectingLogger('Data object not found in search index')
        );

        $item = $handler->apply((new DataObjectSearchResultItem())->setId(self::ELEMENT_ID), new User());

        $this->assertSame([], $item->getSearchIndexData());
        $this->assertFalse($item->isHasChildren());
        $this->assertFalse($item->isHasWorkflowWithPermissions());
        $this->assertSame([], $item->getSearchIndexData(), 'Repeated reads must not repeat the lookup.');
    }

    public function testDocumentItemNotFoundInIndexLogsWarningAndReturnsDefaults(): void
    {
        $searchService = $this->createMock(DocumentSearchServiceInterface::class);
        $searchService->expects($this->once())->method('byId')->willReturn(null);

        $handler = new DocumentLazyLoadingHandler($searchService);
        $handler->setLogger(
            $this->createWarningExpectingLogger('Document not found in search index')
        );

        $item = $handler->apply((new DocumentSearchResultItem())->setId(self::ELEMENT_ID), new User());

        $this->assertSame([], $item->getSearchIndexData());
        $this->assertFalse($item->isHasChildren());
        $this->assertFalse($item->isHasWorkflowWithPermissions());
        $this->assertSame([], $item->getSearchIndexData(), 'Repeated reads must not repeat the lookup.');
    }

    public function testAssetItemNotFoundInIndexLogsWarningAndReturnsDefaults(): void
    {
        $searchService = $this->createMock(AssetSearchServiceInterface::class);
        $searchService->expects($this->once())->method('byId')->willReturn(null);

        $handler = new AssetLazyLoadingHandler($searchService);
        $handler->setLogger(
            $this->createWarningExpectingLogger('Asset not found in search index')
        );

        $item = $handler->apply((new AssetSearchResultItem())->setId(self::ELEMENT_ID), new User());

        $this->assertSame([], $item->getSearchIndexData());
        $this->assertFalse($item->isHasChildren());
        $this->assertFalse($item->isHasWorkflowWithPermissions());
        $this->assertNull($item->getFileSize());
    }

    public function testFailedLookupIsRetriedOnTheNextGetterCall(): void
    {
        $searchService = $this->createMock(DataObjectSearchServiceInterface::class);
        $searchService->expects($this->exactly(2))
            ->method('byId')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('Search index not reachable')),
                (new DataObjectSearchResultItem())
                    ->setHasChildren(true)
                    ->setSearchIndexData(['key' => 'value'])
                    ->setHasWorkflowWithPermissions(true)
            );

        $handler = new DataObjectLazyLoadingHandler($searchService);
        $handler->setLogger($this->createMock(LoggerInterface::class));

        $item = $handler->apply((new DataObjectSearchResultItem())->setId(self::ELEMENT_ID), new User());

        try {
            $item->isHasChildren();
            $this->fail('The lookup exception must not be swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Search index not reachable', $exception->getMessage());
        }

        $this->assertTrue($item->isHasChildren());
        $this->assertSame(['key' => 'value'], $item->getSearchIndexData());
    }

    private function createWarningExpectingLogger(string $message): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($message, ['id' => self::ELEMENT_ID]);

        return $logger;
    }
}
