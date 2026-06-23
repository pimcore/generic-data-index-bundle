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
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class AbstractIndexHandlerReindexMappingTest extends Unit
{
    public function testReindexFailurePropagatesAndDoesNotCallDestructiveFallback(): void
    {
        $original = new Exception('OpenSearch 504 (original)');

        // createIndex/deleteIndex MUST NOT be called — the destructive fallback is gone.
        $searchIndexService = Stub::makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'reindex' => function () use ($original): void {
                throw $original;
            },
            'createIndex' => Expected::never(),
            'deleteIndex' => Expected::never(),
            'putMapping' => Expected::never(),
        ]);

        $handler = $this->makeHandler($searchIndexService);

        try {
            $handler->reindexMapping(null, ['some' => ['type' => 'keyword']]);
            self::fail('Expected an exception to propagate');
        } catch (Exception $e) {
            self::assertSame(
                $original,
                $e,
                'The original exception must propagate unchanged (no shadowing, no wrapping inside the handler)'
            );
        }
    }

    public function testReindexFailedExceptionPropagatesUnchanged(): void
    {
        $wrapped = new ReindexFailedException('Reindex task XYZ failed: foo');

        $searchIndexService = Stub::makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'reindex' => function () use ($wrapped): void {
                throw $wrapped;
            },
            'createIndex' => Expected::never(),
        ]);

        $handler = $this->makeHandler($searchIndexService);

        try {
            $handler->reindexMapping(null, ['some' => ['type' => 'keyword']]);
            self::fail('Expected a ReindexFailedException');
        } catch (ReindexFailedException $e) {
            self::assertSame($wrapped, $e);
        }
    }

    public function testWhenAliasDoesNotExistThenUpdateMappingIsCalledAndReindexIsNot(): void
    {
        $putMappingCalled = false;

        $searchIndexService = Stub::makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => false,
            'getCurrentIndexVersion' => '',
            'createIndex' => function () use (&$fluent) {
                return $fluent;
            },
            'addAlias' => [],
            'reindex' => Expected::never(),
            'putMapping' => function () use (&$putMappingCalled) {
                $putMappingCalled = true;

                return [];
            },
        ]);
        $fluent = $searchIndexService;

        $handler = $this->makeHandler($searchIndexService);

        $handler->reindexMapping(null, ['some' => ['type' => 'keyword']]);

        self::assertTrue($putMappingCalled, 'updateMapping path was taken and doUpdateMapping ran');
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
