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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\UpdateByQueryService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\SearchClient\Exception\ClientException;
use Pimcore\SearchClient\SearchClientInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class UpdateByQueryServiceTest extends Unit
{
    /** What Bonsai answers _update_by_query with below its "Business" plans, as the OpenSearch client reports it. */
    private const string BONSAI_REFUSAL = 'Failed to update by query: {"error":{"root_cause":[{"type":"bonsai_exception",'
        . '"reason":"Update by query is for business+ only"}],"type":"bonsai_exception",'
        . '"reason":"Update by query is for business+ only"},"status":403}';

    private int $nativeCalls = 0;

    /** @var array<int, array> */
    private array $searches = [];

    /** @var array<int, array> */
    private array $bulks = [];

    /** @var array<int, array> */
    private array $refreshes = [];

    public function testUsesTheNativeApiByDefault(): void
    {
        $service = $this->createService([[$this->hit(1)]]);

        $service->updateByQuery($this->params(), $this->setKey(...));

        $this->assertSame(1, $this->nativeCalls);
        $this->assertSame([], $this->searches);
        $this->assertSame([], $this->bulks);
    }

    public function testConfiguredEmulationSkipsTheNativeApi(): void
    {
        $service = $this->createService([[$this->hit(1), $this->hit(2)]], emulationConfigured: true);

        $service->updateByQuery($this->params(), $this->setKey(...));

        $this->assertSame(0, $this->nativeCalls);
        $this->assertCount(1, $this->searches);
        $this->assertSame(
            [
                ['index' => ['_index' => 'idx-odd', '_id' => '1', 'if_seq_no' => 11, 'if_primary_term' => 1]],
                ['system_fields' => ['id' => 1, 'key' => 'new']],
                ['index' => ['_index' => 'idx-odd', '_id' => '2', 'if_seq_no' => 12, 'if_primary_term' => 1]],
                ['system_fields' => ['id' => 2, 'key' => 'new']],
            ],
            $this->bulks[0]['body']
        );
        $this->assertSame([['index' => 'idx']], $this->refreshes);
    }

    public function testFallsBackToTheEmulationOnceTheClusterRefusesTheNativeApi(): void
    {
        $service = $this->createService(
            [[$this->hit(1)], [$this->hit(1)]],
            nativeFailure: new ClientException(self::BONSAI_REFUSAL)
        );

        $service->updateByQuery($this->params(), $this->setKey(...));
        $service->updateByQuery($this->params(), $this->setKey(...));

        // the second request goes straight to the emulation
        $this->assertSame(1, $this->nativeCalls);
        $this->assertCount(2, $this->searches);
        $this->assertCount(2, $this->bulks);
    }

    public function testRecognizesA403StatusCodeAnywhereInTheExceptionChain(): void
    {
        $service = $this->createService(
            [[$this->hit(1)]],
            nativeFailure: new ClientException('Failed to update by query', 0, new Exception('Forbidden', 403))
        );

        $service->updateByQuery($this->params(), $this->setKey(...));

        $this->assertCount(1, $this->bulks);
    }

    public function testRethrowsOtherFailuresOfTheNativeApi(): void
    {
        $failure = new ClientException('Failed to update by query: {"error":{"type":"script_exception"},"status":400}');
        $service = $this->createService([[$this->hit(1)]], nativeFailure: $failure);

        try {
            $service->updateByQuery($this->params(), $this->setKey(...));
            $this->fail('The native failure was swallowed.');
        } catch (ClientException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame([], $this->searches);
    }

    public function testPagesWithSearchAfterAndWritesOnlyChangedDocuments(): void
    {
        $firstPage = array_map(fn (int $id) => $this->hit($id), range(1, 500));
        $firstPage[0]['_source']['system_fields']['key'] = 'new';
        $service = $this->createService([$firstPage, [$this->hit(501)]], emulationConfigured: true);

        $service->updateByQuery($this->params(refresh: false), $this->setKey(...));

        $this->assertCount(2, $this->searches);
        $this->assertArrayNotHasKey('search_after', $this->searches[0]['body']);
        $this->assertSame([500], $this->searches[1]['body']['search_after']);
        $this->assertSame([['system_fields.id' => ['order' => 'asc']]], $this->searches[0]['body']['sort']);
        $this->assertTrue($this->searches[0]['body']['seq_no_primary_term']);
        $this->assertSame(['term' => ['system_fields.key' => 'old']], $this->searches[0]['body']['query']);

        // document 1 already carried the new value
        $this->assertCount(2 * 499, $this->bulks[0]['body']);
        $this->assertSame('2', $this->bulks[0]['body'][0]['index']['_id']);
        $this->assertCount(2, $this->bulks[1]['body']);
        $this->assertSame([], $this->refreshes);
    }

    public function testSkipsVersionConflictsWhenTheRequestProceedsOnConflicts(): void
    {
        $service = $this->createService(
            [[$this->hit(1), $this->hit(2)]],
            emulationConfigured: true,
            bulkResponse: $this->bulkResponse(['_id' => '1', 'status' => 409, 'error' => ['type' => 'version_conflict_engine_exception']])
        );

        $service->updateByQuery($this->params(conflicts: 'proceed'), $this->setKey(...));

        $this->assertCount(1, $this->bulks);
    }

    public function testFailsOnVersionConflictsWhenTheRequestDoesNotProceedOnConflicts(): void
    {
        $service = $this->createService(
            [[$this->hit(1), $this->hit(2)]],
            emulationConfigured: true,
            bulkResponse: $this->bulkResponse(['_id' => '1', 'status' => 409, 'error' => ['type' => 'version_conflict_engine_exception']])
        );

        $this->expectException(BulkOperationException::class);
        $this->expectExceptionMessage('failed for 1 document(s)');

        $service->updateByQuery($this->params(), $this->setKey(...));
    }

    public function testFailsOnOtherBulkErrorsEvenWhenProceedingOnConflicts(): void
    {
        $service = $this->createService(
            [[$this->hit(1), $this->hit(2)]],
            emulationConfigured: true,
            bulkResponse: $this->bulkResponse(['_id' => '2', 'status' => 400, 'error' => ['type' => 'mapper_parsing_exception']])
        );

        $this->expectException(BulkOperationException::class);
        $this->expectExceptionMessage('mapper_parsing_exception');

        $service->updateByQuery($this->params(conflicts: 'proceed'), $this->setKey(...));
    }

    public function testForcedEmulationCanBeSwitchedOffAgain(): void
    {
        $service = $this->createService([[$this->hit(1)]]);

        $service->forceEmulation(true);
        $service->updateByQuery($this->params(), $this->setKey(...));
        $service->forceEmulation(false);
        $service->updateByQuery($this->params(), $this->setKey(...));

        $this->assertCount(1, $this->searches);
        $this->assertSame(1, $this->nativeCalls);
    }

    private function setKey(array $source): array
    {
        $source['system_fields']['key'] = 'new';

        return $source;
    }

    private function params(bool $refresh = true, ?string $conflicts = null): array
    {
        $params = [
            'index' => 'idx',
            'refresh' => $refresh,
            'body' => [
                'script' => ['source' => 'ctx._source.system_fields.key = "new"', 'lang' => 'painless'],
                'query' => ['term' => ['system_fields.key' => 'old']],
            ],
        ];

        if ($conflicts !== null) {
            $params['conflicts'] = $conflicts;
        }

        return $params;
    }

    private function hit(int $id): array
    {
        return [
            '_index' => 'idx-odd',
            '_id' => (string) $id,
            '_seq_no' => 10 + $id,
            '_primary_term' => 1,
            '_source' => ['system_fields' => ['id' => $id, 'key' => 'old']],
            'sort' => [$id],
        ];
    }

    private function bulkResponse(array $failedItem): array
    {
        return [
            'errors' => true,
            'items' => [
                ['index' => $failedItem],
                ['index' => ['_id' => 'x', 'status' => 200]],
            ],
        ];
    }

    /**
     * @param array<int, array> $pages the hits the successive searches return
     */
    private function createService(
        array $pages,
        bool $emulationConfigured = false,
        ?Exception $nativeFailure = null,
        array $bulkResponse = ['errors' => false, 'items' => []],
    ): UpdateByQueryService {
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'updateByQuery' => function () use ($nativeFailure): array {
                $this->nativeCalls++;

                if ($nativeFailure !== null) {
                    throw $nativeFailure;
                }

                return [];
            },
            'search' => function (array $params) use ($pages): array {
                $this->searches[] = $params;

                return ['hits' => ['hits' => $pages[count($this->searches) - 1] ?? []]];
            },
            'bulk' => function (array $params) use ($bulkResponse): array {
                $this->bulks[] = $params;

                return $bulkResponse;
            },
            'refreshIndex' => function (array $params = []): array {
                $this->refreshes[] = $params;

                return [];
            },
        ]);

        $service = new UpdateByQueryService(
            $client,
            $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
                'emulateUpdateByQuery' => $emulationConfigured,
            ]),
        );
        $service->setLogger(new NullLogger());

        return $service;
    }
}
