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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexService\IndexHandler;

use Codeception\Stub;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\AbstractLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * CHARACTERIZATION TESTS — these document the PREVIOUS (buggy) behavior of
 * AbstractIndexHandler::reindexMapping. They are expected to:
 *   - PASS on the un-fixed code (proves the bugs are real and reproducible)
 *   - FAIL on the fixed code (proves the destructive fallback and silent
 *     exception swallowing have been removed)
 *
 * Run these BEFORE applying the fix to verify the bugs exist; then run them
 * AFTER the fix and confirm every test fails. Then delete this file — the
 * post-fix behavior is covered by AbstractIndexHandlerReindexMappingTest.
 */
final class AbstractIndexHandlerCurrentBehaviorTest extends Unit
{
    /**
     * BUG #1: when searchIndexService->reindex() throws (e.g. OpenSearch 5xx),
     * the previous handler silently fell back to updateMapping(forceCreateIndex: true),
     * which calls createIndex() against the live index name — which in turn calls
     * deleteIndex() then createIndex() on the underlying client. The live production
     * index is wiped and recreated empty, and no exception bubbles out.
     */
    public function testReindexFailure_TriggersDestructiveFallback_AndSwallowsException(): void
    {
        $createIndexCalls = [];

        // The fluent-chain return for createIndex()->addAlias()
        $fluentReturn = Stub::makeEmpty(SearchIndexServiceInterface::class, [
            'addAlias' => [],
        ]);

        $searchIndexService = Stub::makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => 'odd',
            'reindex' => function (): void {
                throw new Exception('OpenSearch returned 5xx (simulated timeout)');
            },
            'putMapping' => [],
            'createIndex' => function (string $indexName) use (&$createIndexCalls, $fluentReturn) {
                $createIndexCalls[] = $indexName;

                return $fluentReturn;
            },
            'addAlias' => [],
            'deleteIndex' => [],
        ]);

        $handler = $this->makeHandler($searchIndexService);

        // CURRENT BUG: no exception. After the fix this call will throw.
        $handler->reindexMapping(null, ['some' => ['type' => 'keyword']]);

        // The destructive fallback recreated the live "-odd" index. After the fix
        // the fallback is gone, no exception is swallowed, and this assertion never
        // runs because reindexMapping throws first.
        self::assertContains(
            'pimcore_data-test-alias-odd',
            $createIndexCalls,
            'CURRENT BUG: live production index is being recreated empty after reindex failure'
        );
    }

    /**
     * BUG #2: when both reindex AND the destructive fallback fail, the inner
     * catch shadows the outer exception variable and only logs. No exception
     * propagates, and the original OpenSearch exception text is lost.
     */
    public function testReindexFailure_AndFallbackFailure_AreBothSilentlySwallowed(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var array<int, array{level: string, message: string}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };

        $searchIndexService = Stub::makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => 'odd',
            'reindex' => function (): void {
                throw new Exception('OpenSearch 5xx (THE ORIGINAL exception)');
            },
            'createIndex' => function (): void {
                throw new Exception('fallback createIndex failed');
            },
        ]);

        $handler = $this->makeHandler($searchIndexService);
        $handler->setLogger($logger);

        // CURRENT BUG: returns normally despite TWO consecutive failures.
        // After the fix this throws — assertion never runs and the test fails.
        $handler->reindexMapping(null, ['some' => ['type' => 'keyword']]);

        $errorLogs = array_values(array_filter($logger->records, fn ($r) => $r['level'] === 'error'));
        self::assertNotEmpty($errorLogs, 'inner failure was at least logged');

        $loggedMessage = $errorLogs[0]['message'];

        // CURRENT BUG: the message logged is the fallback's exception, not the original 5xx.
        // After the fix, the log message format changes and includes the original message.
        self::assertStringContainsString('fallback createIndex failed', $loggedMessage);
        self::assertStringNotContainsString(
            'THE ORIGINAL',
            $loggedMessage,
            'CURRENT BUG: variable shadowing discarded the original OpenSearch exception'
        );
    }

    private function makeHandler(SearchIndexServiceInterface $searchIndexService): AbstractIndexHandler
    {
        return new class(
            $searchIndexService,
            Stub::makeEmpty(SearchIndexConfigServiceInterface::class),
            Stub::makeEmpty(EventDispatcherInterface::class),
            Stub::makeEmpty(IndexMappingServiceInterface::class),
        ) extends AbstractIndexHandler {
            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'pimcore_data-test-alias';
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return ['some' => ['type' => 'keyword']];
            }
        };
    }
}
