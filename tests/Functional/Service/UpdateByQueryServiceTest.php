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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Service;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\UpdateByQueryService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\SearchClient\SearchClientInterface;
use Psr\Log\NullLogger;

/**
 * Runs the _update_by_query emulation against the real search cluster.
 */
final class UpdateByQueryServiceTest extends Unit
{
    private const string INDEX = 'gdi_test_emulated_update_by_query';

    protected IndexTester $tester;

    private SearchClientInterface $client;

    protected function _before(): void
    {
        $this->client = $this->tester->getIndexSearchClient();
        $this->deleteTestIndex();
        $this->client->createIndex(['index' => self::INDEX]);
    }

    protected function _after(): void
    {
        $this->deleteTestIndex();
    }

    /**
     * Like the native API, the emulation reads from the snapshot taken for the first page. A document changed
     * before its page is read therefore fails the sequence number check and is skipped (conflicts=proceed),
     * instead of being read in its new state and having that change overwritten.
     */
    public function testKeepsAChangeMadeToADocumentOfALaterPage(): void
    {
        $this->indexDocument(1, 'propagate');
        $this->indexDocument(2, 'propagate');
        $this->client->refreshIndex(['index' => self::INDEX]);

        $service = new UpdateByQueryService(
            $this->client,
            $this->tester->grabService(SearchIndexConfigServiceInterface::class),
            batchSize: 1
        );
        $service->setLogger(new NullLogger());
        $service->forceEmulation(true);

        $changedConcurrently = null;
        $service->updateByQuery(
            [
                'index' => self::INDEX,
                'refresh' => true,
                'conflicts' => 'proceed',
                'body' => ['query' => ['match_all' => new \stdClass()]],
            ],
            function (array $source) use (&$changedConcurrently): array {
                // While the first page is written, the document of the second page gets locked by someone else.
                if ($changedConcurrently === null) {
                    $changedConcurrently = $source['system_fields']['id'] === 1 ? 2 : 1;
                    $this->indexDocument($changedConcurrently, 'self');
                    $this->client->refreshIndex(['index' => self::INDEX]);
                }

                $source['system_fields']['locked'] = null;

                return $source;
            }
        );

        $unlocked = $changedConcurrently === 1 ? 2 : 1;
        $this->assertNull($this->getLocked($unlocked));
        $this->assertSame('self', $this->getLocked($changedConcurrently));
    }

    private function indexDocument(int $id, ?string $locked): void
    {
        $this->client->index([
            'index' => self::INDEX,
            'id' => (string) $id,
            'body' => ['system_fields' => ['id' => $id, 'locked' => $locked]],
        ]);
    }

    private function getLocked(int $id): ?string
    {
        return $this->client->get(['index' => self::INDEX, 'id' => (string) $id])['_source']['system_fields']['locked'];
    }

    private function deleteTestIndex(): void
    {
        if ($this->client->existsIndex(['index' => self::INDEX])) {
            $this->client->deleteIndex(['index' => self::INDEX]);
        }
    }
}
