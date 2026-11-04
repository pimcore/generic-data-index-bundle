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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingChangeStrategy;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex\ReindexOutcome;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLock;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexService\RecordingLockStore;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Every mapping update of an index runs under the lock of its alias, so two processes never copy or recreate the
 * same index at the same time.
 *
 * @see https://github.com/pimcore/platform-version/issues/641
 *
 * @internal
 */
final class AbstractIndexHandlerLockTest extends Unit
{
    private const RESOURCE = 'generic-data-index:mapping-update:test_alias';

    public function testTheCopyRunsUnderTheLockOfTheIndex(): void
    {
        $store = new RecordingLockStore();
        $heldDuringCopy = null;
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'reindexWithoutFields' => static function () use ($store, &$heldDuringCopy): ReindexOutcome {
                $heldDuringCopy = $store->getHeldResources();

                return new ReindexOutcome(ReindexResult::SUCCESS);
            },
        ]);
        $handler = $this->createHandler($search, $store);

        $handler->reindexMapping();

        $this->assertSame([self::RESOURCE], $heldDuringCopy);
        $this->assertSame([], $store->getHeldResources(), 'the lock is released after the copy');
        $this->assertSame(1, $store->getSaves());
    }

    public function testUpdateMappingRunsUnderTheLockOfTheIndex(): void
    {
        $store = new RecordingLockStore();
        $heldDuringPutMapping = null;
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => 'even',
            'putMapping' => static function () use ($store, &$heldDuringPutMapping): array {
                $heldDuringPutMapping = $store->getHeldResources();

                return [];
            },
        ]);
        $handler = $this->createHandler($search, $store);

        $handler->updateMapping();

        $this->assertSame([self::RESOURCE], $heldDuringPutMapping);
        $this->assertSame([], $store->getHeldResources());
    }

    public function testTheRecreationAfterAnIncompatibleCopyRunsUnderTheSameLock(): void
    {
        $store = new RecordingLockStore();
        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, ['addAlias' => []]);
        $heldDuringRecreation = [];
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => '',
            'reindexWithoutFields' => new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE),
            'createIndex' => static function (string $indexName) use ($store, $fluent, &$heldDuringRecreation) {
                $heldDuringRecreation[$indexName] = $store->getHeldResources();

                return $fluent;
            },
            'putMapping' => [],
        ]);
        $handler = $this->createHandler($search, $store);

        $handler->reindexMapping();

        $this->assertSame(['test_alias-odd' => [self::RESOURCE]], $heldDuringRecreation);
        $this->assertSame(1, $store->getSaves(), 'the copy and the recreation share one lock');
        $this->assertGreaterThan(1, $store->getRefreshes(), 'the lock is refreshed before the further attempt');
        $this->assertSame([], $store->getHeldResources());
    }

    public function testAnOverriddenReindexMappingRunsUnderTheLockAlreadyHeld(): void
    {
        $store = new RecordingLockStore();
        $copies = 0;
        $heldDuringCopy = null;
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'reindexWithoutFields' => static function () use ($store, &$copies, &$heldDuringCopy): ReindexOutcome {
                ++$copies;
                $heldDuringCopy = $store->getHeldResources();

                return new ReindexOutcome(ReindexResult::SUCCESS);
            },
        ]);
        $handler = $this->createOverridingHandler($search, $store);

        $result = $handler->reindexMappingWithResult();

        $this->assertSame(1, $copies);
        $this->assertSame([self::RESOURCE], $heldDuringCopy);
        $this->assertSame(1, $store->getSaves(), 'the override runs inside the lock of reindexMappingWithResult()');
        $this->assertTrue($result->isOutcomeUnknown());
        $this->assertSame([], $store->getHeldResources());
    }

    public function testTheLockIsReleasedWhenTheCopyFails(): void
    {
        $store = new RecordingLockStore();
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'reindexWithoutFields' => static function (): never {
                throw new RuntimeException('cluster unreachable');
            },
        ]);
        $handler = $this->createHandler($search, $store);

        try {
            $handler->reindexMapping();
            $this->fail('the failure must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('cluster unreachable', $e->getMessage());
        }

        $this->assertSame([], $store->getHeldResources());
    }

    private function createHandler(SearchIndexServiceInterface $search, RecordingLockStore $store): AbstractIndexHandler
    {
        [$config, $events, $mapping, $copy, $lock] = $this->dependencies($search, $store);

        $handler = new class($search, $config, $events, $mapping, $copy, $lock) extends AbstractIndexHandler {
            protected function extractMappingProperties(mixed $context = null): array
            {
                return ['standard_fields' => ['properties' => ['price' => ['type' => 'float']]]];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());

        return $handler;
    }

    /**
     * A subclass as written against 2.5.14, overriding reindexMapping() and delegating to the parent.
     */
    private function createOverridingHandler(
        SearchIndexServiceInterface $search,
        RecordingLockStore $store
    ): AbstractIndexHandler {
        [$config, $events, $mapping, $copy, $lock] = $this->dependencies($search, $store);

        $handler = new class($search, $config, $events, $mapping, $copy, $lock) extends AbstractIndexHandler {
            public function reindexMapping(
                ?ClassDefinition $context = null,
                ?array $mappingProperties = null
            ): void {
                parent::reindexMapping($context, $mappingProperties);
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return ['standard_fields' => ['properties' => ['price' => ['type' => 'float']]]];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());

        return $handler;
    }

    /**
     * @return array{
     *     SearchIndexConfigServiceInterface,
     *     EventDispatcherInterface,
     *     IndexMappingServiceInterface,
     *     IndexCopyService,
     *     IndexLock
     * }
     */
    private function dependencies(SearchIndexServiceInterface $search, RecordingLockStore $store): array
    {
        return [
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(EventDispatcherInterface::class),
            $this->makeEmpty(IndexMappingServiceInterface::class),
            new IndexCopyService($search, new MappingDiffService(), new NullLogger(), MappingChangeStrategy::COPY->value),
            new IndexLock(new LockFactory($store), 1, 1),
        ];
    }
}
