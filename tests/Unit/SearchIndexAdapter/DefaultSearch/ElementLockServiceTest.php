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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\ElementLockService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\LockedElementsCache;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\UpdateByQueryServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\PathService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AbstractElementTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class ElementLockServiceTest extends Unit
{
    /** @var array<int, array> */
    private array $searches = [];

    public function testLockStatesOfManyElementsNeedOneSearchPerIndex(): void
    {
        $service = $this->createService([
            ['/campaigns', 'propagate'],
            ['/products/shoes/sneaker-1', 'self'],
        ]);

        $this->assertTrue($service->isElementLocked('/campaigns/2026/week-1', 'object'), 'propagate on an ancestor');
        $this->assertTrue($service->isElementLocked('/campaigns/2026', 'object'), 'propagate on the parent');
        $this->assertTrue($service->isElementLocked('/products', 'object'), 'locked descendant, any depth');
        $this->assertTrue($service->isElementLocked('/products/shoes', 'object'), 'locked child');
        $this->assertFalse($service->isElementLocked('/products/shoes/sneaker-1/variant', 'object'), 'self lock does not propagate');
        $this->assertFalse($service->isElementLocked('/products/shoe', 'object'), 'path prefix without separator');
        $this->assertFalse($service->isElementLocked('/campaigns', 'object'), 'own lock is passed by the caller');
        $this->assertFalse($service->isElementLocked('/other', 'object'));

        $this->assertCount(1, $this->searches, 'one search loads all locks of the index');
    }

    /**
     * The combined element-search index holds assets, documents and data objects, which can share
     * a full path. A "self" lock must not hide a "propagate" lock on the same path, in either order.
     */
    public function testPropagateLockWinsOverSelfLockOnTheSamePath(): void
    {
        foreach ([['propagate', 'self'], ['self', 'propagate']] as $order) {
            $this->searches = [];
            $service = $this->createService([['/shared', $order[0]], ['/shared', $order[1]]]);

            $this->assertTrue(
                $service->isElementLocked('/shared/child', 'element-search'),
                'propagate lock found with hit order ' . implode(', ', $order)
            );
        }
    }

    public function testOwnLockAndRootDoNotQueryTheIndex(): void
    {
        $service = $this->createService([]);

        $this->assertTrue($service->isElementLocked('/anything', 'object', 'self'));
        $this->assertFalse($service->isElementLocked('/', 'object'));

        $this->assertCount(0, $this->searches);
    }

    public function testFallsBackToPerElementQueriesWhenTheIndexHasTooManyLocks(): void
    {
        $service = $this->createService([['/a', 'propagate']], totalLocks: 100000, perElementCount: 1);

        $this->assertTrue($service->isElementLocked('/x', 'object'));
        $this->assertTrue($service->isElementLocked('/y', 'object'));

        // 1 search for the lock list, then the existing per-element count query for each check
        $this->assertCount(3, $this->searches);
    }

    public function testResetClearsTheLoadedLocks(): void
    {
        $cache = new LockedElementsCache();
        $service = $this->createService([['/a', 'propagate']], cache: $cache);

        $service->isElementLocked('/a/b', 'object');
        $cache->reset();
        $service->isElementLocked('/a/b', 'object');

        $this->assertCount(2, $this->searches);
    }

    public function testUnlockPropagateClearsTheLoadedLocks(): void
    {
        $service = $this->createService([['/a', 'propagate']]);

        $service->isElementLocked('/a/b', 'asset');
        $service->unlockPropagate($this->makeEmpty(Asset::class, ['getRealFullPath' => '/a']));
        $service->isElementLocked('/a/b', 'asset');

        $this->assertCount(2, $this->searches);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $locks full path and lock value of locked elements
     */
    private function createService(
        array $locks,
        int $totalLocks = -1,
        int $perElementCount = 0,
        ?LockedElementsCache $cache = null
    ): ElementLockService {
        $hits = array_map(static fn (array $lock) => [
            '_source' => ['system_fields' => ['fullPath' => $lock[0], 'locked' => $lock[1]]],
        ], $locks);
        $total = $totalLocks >= 0 ? $totalLocks : count($locks);

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'search' => function (array $params) use ($hits, $total, $perElementCount): array {
                $this->searches[] = $params;

                if (count($this->searches) === 1) {
                    return ['hits' => ['total' => ['value' => $total], 'hits' => $total > count($hits) ? [] : $hits]];
                }

                return ['hits' => ['total' => ['value' => $perElementCount], 'hits' => []]];
            },
        ]);

        return new ElementLockService(
            $client,
            $this->makeEmpty(AdapterServiceInterface::class, [
                'getTypeAdapter' => $this->makeEmpty(
                    AbstractElementTypeAdapter::class,
                    ['getAliasIndexNameByElement' => 'assets']
                ),
            ]),
            new PathService(),
            $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
                'getIndexName' => static fn (string $name) => 'pimcore_' . $name,
            ]),
            $cache ?? new LockedElementsCache(),
            $this->makeEmpty(UpdateByQueryServiceInterface::class),
        );
    }
}
