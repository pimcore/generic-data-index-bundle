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

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
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
     * When updateMapping() exhausts all retry attempts (MAX_REINDEX_ATTEMPTS), the resulting
     * ReindexFailedException must propagate to the caller instead of being swallowed.
     * Otherwise IndexUpdateService::updateClassDefinition() stores the mapping checksum as if
     * the update succeeded, leaving the index silently out of sync and never retried.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/issues/471
     */
    public function testUpdateMappingPropagatesReindexFailedException(): void
    {
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, ['addAlias' => []]);

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'putMapping' => static function (): array {
                throw new Exception('putMapping failed');
            },
            'reindex' => ReindexResult::MAPPING_INCOMPATIBLE,
            'createIndex' => static function () use ($fluent): SearchIndexServiceInterface {
                return $fluent;
            },
            'deleteIndex' => null,
            'existsIndex' => false,
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $thrown = null;

        try {
            $handler->updateMapping();
        } catch (ReindexFailedException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(
            ReindexFailedException::class,
            $thrown,
            'updateMapping() must propagate ReindexFailedException so the mapping checksum is never stored on failure'
        );
    }

    /**
     * When the in-place reindex fails (e.g. a 5xx from OpenSearch) and the fallback
     * index recreation fails too, the failure must propagate to the caller. Otherwise
     * the mapping checksum gets stored as if the reindex succeeded and the class is
     * never retried on subsequent deployments.
     *
     * @see https://github.com/pimcore/service-operations/issues/853
     */
    public function testReindexMappingPropagatesRecreationFailures(): void
    {
        $recreationException = new Exception('index recreation failed');

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'reindex' => ReindexResult::MAPPING_INCOMPATIBLE,
            'createIndex' => static function () use ($recreationException): void {
                throw $recreationException;
            },
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $thrown = null;

        try {
            $handler->reindexMapping();
        } catch (Exception $e) {
            $thrown = $e;
        }

        $this->assertSame(
            $recreationException,
            $thrown,
            'A failed index recreation must propagate so the mapping checksum is not stored'
        );
    }

    /**
     * A transient failure (unreachable cluster, timeout, rejected request) must
     * propagate without touching any index. Recreating the live index in reaction
     * to a transient error destroys all indexed data.
     *
     * @see https://github.com/pimcore/service-operations/issues/1126
     */
    public function testReindexMappingPropagatesTransientFailuresWithoutTouchingIndices(): void
    {
        $transientException = new Exception('No alive nodes found in your cluster');

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'reindex' => static function () use ($transientException): void {
                throw $transientException;
            },
            'createIndex' => Expected::never(),
            'deleteIndex' => Expected::never(),
            'putMapping' => Expected::never(),
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $thrown = null;

        try {
            $handler->reindexMapping();
        } catch (Exception $e) {
            $thrown = $e;
        }

        $this->assertSame(
            $transientException,
            $thrown,
            'A transient reindex failure must propagate unchanged and must not trigger index recreation'
        );
    }

    /**
     * A forced index recreation is the designed recovery for mapping changes that
     * cannot be applied to the existing documents via reindex — reported by the
     * adapter as MAPPING_INCOMPATIBLE, never inferred from an exception.
     */
    public function testReindexMappingRecreatesIndexWhenMappingIsIncompatible(): void
    {
        $createdIndices = [];
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'addAlias' => [],
        ]);

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'reindex' => ReindexResult::MAPPING_INCOMPATIBLE,
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
            'The index must be recreated when the mapping is incompatible'
        );
    }

    public function testReindexMappingDoesNotRecreateIndexOnSuccess(): void
    {
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'reindex' => ReindexResult::SUCCESS,
            'createIndex' => Expected::never(),
            'deleteIndex' => Expected::never(),
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $handler->reindexMapping();
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
     * doUpdateMapping() always fails with an exception from putMapping(), the
     * recursive re-entry must be bounded: after MAX_REINDEX_ATTEMPTS the method
     * must throw ReindexFailedException instead of overflowing the stack.
     *
     * This test starts from the default arguments (depth = 0) so that the entire
     * alias-missing → doUpdateMappingFull → doReindexMapping → putMapping failure
     * cycle is exercised, not just the terminal guard.
     *
     * reindexMapping() is the public entry-point and must propagate ReindexFailedException
     * so callers do not store a mapping checksum for an update that was never applied.
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
            'createIndex' => static function () use ($fluent): SearchIndexServiceInterface {
                return $fluent;
            },
            'putMapping' => static function () use (&$attempts): array {
                ++$attempts;

                throw new Exception('AWS rejected empty array in mapping');
            },
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $thrown = null;

        try {
            $handler->reindexMapping();
        } catch (ReindexFailedException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(
            ReindexFailedException::class,
            $thrown,
            'reindexMapping() must throw ReindexFailedException after the bounded number of attempts'
        );
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
     * When reindexMapping() returns MAPPING_INCOMPATIBLE and the subsequent index recreation
     * fails on putMapping(), the failure must propagate so callers do not store a mapping
     * checksum for an update that was never applied.
     *
     * Note: In the ReindexResult model, transient failures (thrown exceptions from reindex())
     * propagate immediately. Only MAPPING_INCOMPATIBLE triggers index recreation and the
     * depth guard applies to that recreation path to prevent infinite recursion.
     */
    public function testReindexMappingBoundsAttemptsWhenMappingIncompatibleAndRecreationFails(): void
    {
        $attempts = 0;
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, ['addAlias' => []]);

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'existsIndex' => false,
            'deleteIndex' => null,
            'getCurrentIndexVersion' => '',
            'reindex' => ReindexResult::MAPPING_INCOMPATIBLE,
            'createIndex' => static function () use ($fluent): SearchIndexServiceInterface {
                return $fluent;
            },
            'putMapping' => static function () use (&$attempts): array {
                ++$attempts;

                throw new Exception('putMapping failed after forced recreation');
            },
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $thrown = null;

        try {
            $handler->reindexMapping();
        } catch (Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull(
            $thrown,
            'reindexMapping() must throw when index recreation fails after MAPPING_INCOMPATIBLE'
        );
        $this->assertGreaterThan(
            0,
            $attempts,
            'putMapping must have been called at least once through the MAPPING_INCOMPATIBLE recreation path'
        );
        $this->assertLessThanOrEqual(
            3,
            $attempts,
            'The number of putMapping attempts must be bounded to MAX_REINDEX_ATTEMPTS to prevent stack overflow'
        );
    }

    /**
     * When the depth guard fires, the ReindexFailedException must carry the exception
     * that last triggered the retry as its $previous, so callers can inspect the full
     * causal chain. This test catches the propagated exception from reindexMapping() and
     * asserts both the wrapper message and the chained cause are present.
     */
    public function testReindexMappingPreservesCauseInReindexFailedException(): void
    {
        $cause = new Exception('AWS rejected empty array in mapping');
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, ['addAlias' => []]);

        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => false,
            'existsIndex' => false,
            'deleteIndex' => null,
            'getCurrentIndexVersion' => '',
            'createIndex' => static function () use ($fluent): SearchIndexServiceInterface {
                return $fluent;
            },
            'putMapping' => static function () use ($cause): array {
                throw $cause;
            },
        ]);

        $handler = $this->createHandlerWithService($searchIndexService);
        $handler->setLogger(new NullLogger());

        $thrown = null;

        try {
            $handler->reindexMapping();
        } catch (ReindexFailedException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(
            ReindexFailedException::class,
            $thrown,
            'reindexMapping() must throw ReindexFailedException after all attempts are exhausted'
        );

        // The wrapper message must be set.
        $this->assertStringContainsString(
            'Max reindex attempts reached',
            $thrown->getMessage(),
            'The ReindexFailedException wrapper message must be correct'
        );

        // The $previous cause must be chained — removing the $cause argument from the
        // ReindexFailedException constructor would break this assertion.
        $this->assertSame(
            $cause,
            $thrown->getPrevious(),
            'The exception that triggered the final retry must be set as the previous exception'
        );
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
        return new class($searchIndexService, $this->makeEmpty(SearchIndexConfigServiceInterface::class), $this->makeEmpty(EventDispatcherInterface::class), $this->makeEmpty(IndexMappingServiceInterface::class), $mappingProperties) extends AbstractIndexHandler {
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
