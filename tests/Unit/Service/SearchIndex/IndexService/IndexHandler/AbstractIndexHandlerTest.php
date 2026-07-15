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

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class AbstractIndexHandlerTest extends Unit
{
    private const ALIAS_NAME = 'test_alias';

    /**
     * An interrupted reindex can leave the alias missing while indexing traffic
     * auto-creates a concrete index carrying the exact alias name. Attaching the
     * alias then fails with invalid_alias_name_exception, so the recovery in
     * updateMapping() must remove such an index before recreating.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/issues/412
     */
    public function testUpdateMappingDeletesIndexSquattingOnAliasName(): void
    {
        $deletedIndices = [];
        $handler = $this->createHandler(
            indexSquatsAliasName: true,
            deletedIndices: $deletedIndices
        );

        $handler->updateMapping();

        $this->assertContains(self::ALIAS_NAME . '-even', $deletedIndices);
        $this->assertContains(self::ALIAS_NAME . '-odd', $deletedIndices);
        $this->assertContains(
            self::ALIAS_NAME,
            $deletedIndices,
            'An index occupying the alias name must be deleted before the alias is attached'
        );
    }

    public function testUpdateMappingKeepsAliasNameUntouchedWhenNoIndexSquatsOnIt(): void
    {
        $deletedIndices = [];
        $handler = $this->createHandler(
            indexSquatsAliasName: false,
            deletedIndices: $deletedIndices
        );

        $handler->updateMapping();

        $this->assertNotContains(self::ALIAS_NAME, $deletedIndices);
    }

    /**
     * When the in-place reindex fails (e.g. a 5xx from OpenSearch) and the fallback
     * index recreation fails too, the failure must propagate to the caller. Otherwise
     * the mapping checksum gets stored as if the reindex succeeded and the class is
     * never retried on subsequent deployments.
     *
     * @see https://github.com/pimcore/service-operations/issues/853
     */
    public function testReindexMappingRethrowsWhenFallbackRecreationAlsoFails(): void
    {
        $reindexException = new Exception('initial reindex failure (504 Gateway Time-out)');
        $fallbackException = new Exception('fallback recreation failed');

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'reindex' => static function () use ($reindexException): void {
                throw $reindexException;
            },
            'createIndex' => static function () use ($fallbackException): void {
                throw $fallbackException;
            },
        ]);

        $logger = $this->createCollectingLogger();
        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger($logger);

        $thrown = null;

        try {
            $handler->reindexMapping();
        } catch (Exception $e) {
            $thrown = $e;
        }

        $this->assertSame(
            $fallbackException,
            $thrown,
            'Expected the fallback exception to propagate out of reindexMapping()'
        );

        $errorLogs = array_filter($logger->records, static fn (array $r): bool => $r['level'] === 'error');
        $this->assertNotEmpty($errorLogs, 'The failure must still be logged');
        $loggedMessage = implode(' | ', array_column($errorLogs, 'message'));
        $this->assertStringContainsString(
            'initial reindex failure',
            $loggedMessage,
            'The original reindex exception must not be lost through variable shadowing'
        );
    }

    /**
     * The fallback to a forced index recreation is the designed recovery for mapping
     * changes that cannot be applied via reindex. When it succeeds, no exception
     * may propagate.
     */
    public function testReindexMappingRecoversWhenFallbackRecreationSucceeds(): void
    {
        $createdIndices = [];
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'addAlias' => [],
        ]);

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'reindex' => static function (): void {
                throw new Exception('initial reindex failure (504 Gateway Time-out)');
            },
            'createIndex' => static function (string $indexName) use (&$createdIndices, $fluent) {
                $createdIndices[] = $indexName;

                return $fluent;
            },
            'putMapping' => [],
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $handler->reindexMapping();

        $this->assertContains(
            self::ALIAS_NAME . '-odd',
            $createdIndices,
            'The fallback must recreate the index when the reindex fails'
        );
    }

    private function createCollectingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var array<int, array{level: string, message: string}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };
    }

    private function createHandler(bool $indexSquatsAliasName, array &$deletedIndices): AbstractIndexHandler
    {
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => false,
            'existsIndex' => static fn (string $indexName): bool => $indexSquatsAliasName
                && $indexName === self::ALIAS_NAME,
            'deleteIndex' => static function ($indexName, bool $silent = false) use (&$deletedIndices): void {
                $deletedIndices[] = $indexName;
            },
            'getCurrentIndexVersion' => '',
            'putMapping' => [],
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        return $handler;
    }

    private function createHandlerWithService(SearchIndexServiceInterface $searchIndexService): AbstractIndexHandler
    {
        return new class($searchIndexService, $this->makeEmpty(SearchIndexConfigServiceInterface::class), $this->makeEmpty(EventDispatcherInterface::class), $this->makeEmpty(IndexMappingServiceInterface::class), ) extends AbstractIndexHandler {
            protected function extractMappingProperties(mixed $context = null): array
            {
                return [];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
    }
}
