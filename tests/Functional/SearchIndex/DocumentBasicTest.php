<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Functional\SearchIndex;

use Codeception\Test\Unit;
use Exception;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Email;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Folder;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\HardLink;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Link;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Page;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Snippet;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * @internal
 */
final class DocumentBasicTest extends Unit
{
    protected IndexTester $tester;

    private DocumentSearchServiceInterface $documentSearchService;

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->documentSearchService = $this->tester->grabService(DocumentSearchServiceInterface::class);
    }

    protected function _after(): void
    {
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testDocumentIndexing()
    {
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName('document');

        $document = TestHelper::createEmptyDocument();
        $documentId = (string)$document->getId();

        // check indexed
        $response = $this->tester->checkIndexEntry($documentId, $indexName);
        $this->assertEquals($documentId, $response['_source']['system_fields']['id']);

        $document->setKey('my-test-document');
        $document->save();

        $response = $this->tester->checkIndexEntry($documentId, $indexName);
        $this->assertEquals($document->getKey(), $response['_source']['system_fields']['key']);

        $document->delete();

        $this->expectException(Missing404Exception::class);
        $this->tester->checkIndexEntry($documentId, $indexName);
    }

    /**
     * @throws Exception
     */
    public function testDocumentSearch()
    {
        $document = TestHelper::createEmptyDocument();
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $documentSearch = $searchProvider
            ->createDocumentSearch()
            ->setPageSize(20);

        $searchResult = $this->documentSearchService->search($documentSearch);

        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals(1, $searchResult->getPagination()->getTotalItems());
        $this->assertEquals(20, $searchResult->getPagination()->getPageSize());
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($document->getId(), $searchResult->getIds()[0]);
    }

    /**
     * @throws Exception
     */
    public function testDocumentSearchTypes()
    {
        $page = TestHelper::createEmptyDocument();
        $folder = TestHelper::createEmptyDocument(
            '', true, true, '\\Pimcore\\Model\\Document\\Folder'
        );
        $email = TestHelper::createEmptyDocument(
            '', true, true, '\\Pimcore\\Model\\Document\\Email'
        );
        $hardLink = TestHelper::createEmptyDocument(
            '', true, true, '\\Pimcore\\Model\\Document\\Hardlink'
        );
        $link = TestHelper::createEmptyDocument(
            '', true, true, '\\Pimcore\\Model\\Document\\Link'
        );
        $snippet = TestHelper::createEmptyDocument(
            '', true, true, '\\Pimcore\\Model\\Document\\Snippet'
        );

        $this->assertInstanceOf(Page::class, $this->documentSearchService->byId($page->getId()));
        $this->assertInstanceOf(Folder::class, $this->documentSearchService->byId($folder->getId()));
        $this->assertInstanceOf(Email::class, $this->documentSearchService->byId($email->getId()));
        $this->assertInstanceOf(HardLink::class, $this->documentSearchService->byId($hardLink->getId()));
        $this->assertInstanceOf(Link::class, $this->documentSearchService->byId($link->getId()));
        $this->assertInstanceOf(Snippet::class, $this->documentSearchService->byId($snippet->getId()));
    }
}
