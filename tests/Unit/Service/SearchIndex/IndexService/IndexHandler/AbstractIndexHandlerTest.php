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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
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

    /**
     * When reindexMapping() enters the alias-missing path and the resulting
     * updateMapping() always fails with an exception from putMapping(), the
     * recursive re-entry must be bounded: after MAX_REINDEX_ATTEMPTS the method
     * must throw ReindexFailedException instead of overflowing the stack.
     *
     * This test starts from the default arguments (depth = 0) so that the entire
     * alias-missing → updateMapping → doUpdateMapping → putMapping failure cycle
     * is exercised, not just the terminal guard.
     */
    public function testReindexMappingThrowsWhenMaxAttemptsReachedFromDefaultArgs(): void
    {
        $attempts = 0;
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, ['addAlias' => []]);

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => false,
            'existsIndex' => false,
            'deleteIndex' => null,
            'getCurrentIndexVersion' => '',
            'createIndex' => static function () use ($fluent): SearchIndexServiceInterface { return $fluent; },
            'putMapping' => static function () use (&$attempts): array {
                ++$attempts;
                throw new Exception('AWS rejected empty array in mapping');
            },
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        // reindexMapping() must complete without overflowing the stack.
        // ReindexFailedException is caught and logged inside updateMapping(), so it does
        // not propagate out to the caller; what matters is that the number of attempts
        // is strictly bounded to MAX_REINDEX_ATTEMPTS (3).
        $handler->reindexMapping();

        $this->assertGreaterThan(
            0,
            $attempts,
            'putMapping must have been called at least once through the real recursive path'
        );
        $this->assertLessThanOrEqual(
            3,
            $attempts,
            'The number of putMapping attempts must be bounded to MAX_REINDEX_ATTEMPTS to prevent stack overflow'
        );
    }

    /**
     * When the alias exists, reindex() fails, and the fallback updateMapping() also fails
     * on putMapping(), the depth counter must still be forwarded so the recursive calls
     * remain bounded. Without the fix, every fallback resets depth to 0, enabling infinite
     * recursion. With the fix, putMapping is called at most MAX_REINDEX_ATTEMPTS times.
     */
    public function testReindexMappingBoundsAttemptsWhenAliasExistsAndBothOperationsFail(): void
    {
        $attempts = 0;
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, ['addAlias' => []]);

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'existsIndex' => false,
            'deleteIndex' => null,
            'getCurrentIndexVersion' => '',
            'reindex' => static function (): void {
                throw new Exception('reindex failed (504 Gateway Time-out)');
            },
            'createIndex' => static function () use ($fluent): SearchIndexServiceInterface { return $fluent; },
            'putMapping' => static function () use (&$attempts): array {
                ++$attempts;
                throw new Exception('putMapping failed after forced recreation');
            },
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        // The first call comes from the alias-present branch: reindex() fails, fallback
        // updateMapping(forceCreate=true) is called, putMapping() inside it fails, which
        // triggers reindexMapping(depth+1). Without depth forwarding this loops forever.
        // With the fix the recursion is capped at MAX_REINDEX_ATTEMPTS and an exception
        // propagates out of reindexMapping().
        $thrown = null;
        try {
            $handler->reindexMapping();
        } catch (Exception $e) {
            $thrown = $e;
        }

        $this->assertGreaterThan(
            0,
            $attempts,
            'putMapping must have been called at least once through the alias-present fallback path'
        );
        $this->assertLessThanOrEqual(
            3,
            $attempts,
            'The number of putMapping attempts must be bounded to MAX_REINDEX_ATTEMPTS to prevent stack overflow'
        );
        // After the depth guard fires the ReindexFailedException propagates out from the
        // alias-missing updateMapping path; the alias-present catch re-throws it.
        $this->assertNotNull($thrown, 'An exception must propagate when all attempts are exhausted');
    }

    /**
     * When extractMappingProperties() returns an empty array, doUpdateMapping() must
     * omit the "properties" key from the putMapping body entirely so that
     * OpenSearch/Elasticsearch never receives "properties":[].
     */
    public function testDoUpdateMappingOmitsPropertiesKeyWhenMappingIsEmpty(): void
    {
        $capturedParams = null;

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'putMapping' => static function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;

                return [];
            },
        ]);

        $handler = $this->createHandlerWithServiceAndMapping(
            $searchIndexService,
            // extractMappingProperties returns an empty mapping
            []
        );
        $handler->setLogger(new NullLogger());

        $handler->updateMapping();

        $this->assertNotNull($capturedParams, 'putMapping must have been called');
        $this->assertArrayNotHasKey(
            'properties',
            $capturedParams['body'],
            'The "properties" key must be omitted from the putMapping body when the mapping is empty'
        );
    }

    /**
     * When extractMappingProperties() returns a non-empty mapping, doUpdateMapping() must
     * include the "properties" key in the putMapping body and pass the raw mapping through
     * (normalization of empty arrays is handled by DefaultSearchService::putMapping()).
     */
    public function testDoUpdateMappingIncludesPropertiesKeyWhenMappingIsNonEmpty(): void
    {
        $capturedParams = null;

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'putMapping' => static function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;

                return [];
            },
        ]);

        $handler = $this->createHandlerWithServiceAndMapping(
            $searchIndexService,
            [
                'my_field' => [
                    'type' => 'keyword',
                ],
            ]
        );
        $handler->setLogger(new NullLogger());

        $handler->updateMapping();

        $this->assertNotNull($capturedParams, 'putMapping must have been called');
        $this->assertArrayHasKey(
            'properties',
            $capturedParams['body'],
            'The "properties" key must be present in the putMapping body when the mapping is non-empty'
        );
        $this->assertArrayHasKey(
            'my_field',
            $capturedParams['body']['properties'],
            'Non-empty mapping fields must be passed through to putMapping'
        );
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

    private function createHandlerWithServiceAndMapping(
        SearchIndexServiceInterface $searchIndexService,
        array $mappingProperties
    ): AbstractIndexHandler {
        return new class(
            $searchIndexService,
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(EventDispatcherInterface::class),
            $this->makeEmpty(IndexMappingServiceInterface::class),
            $mappingProperties
        ) extends AbstractIndexHandler {
            public function __construct(
                SearchIndexServiceInterface $searchIndexService,
                SearchIndexConfigServiceInterface $searchIndexConfigService,
                EventDispatcherInterface $eventDispatcher,
                IndexMappingServiceInterface $indexMappingService,
                private readonly array $properties
            ) {
                parent::__construct($searchIndexService, $searchIndexConfigService, $eventDispatcher, $indexMappingService);
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return $this->properties;
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
    }
}
