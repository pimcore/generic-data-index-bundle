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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Status;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexRebuildMarker;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexRebuildStateService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;

/**
 * The marker goes through enqueuing, enqueued and claimed, by exactly one claimant, per index.
 *
 * @internal
 */
final class IndexRebuildStateServiceTest extends Unit
{
    public function testTheMarkerIsStoredPerIndexAndGoesThroughItsPhases(): void
    {
        $store = [];
        $service = $this->createService($store, 1700000000123);
        $target = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);

        $this->assertNull($service->getMarker($target));

        $marker = $service->markEnqueuing($target);
        $this->assertSame(['asset_rebuild_queued_at' => '{"queuedAt":1700000000123,"enqueued":false}'], $store);
        $this->assertSame(1700000000123, $service->getMarker($target)?->getQueuedAt());
        $this->assertFalse($service->getMarker($target)?->isEnqueued());
        $this->assertNull($service->getMarker(IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS)));

        $this->assertTrue($service->markEnqueued($target, $marker));
        $this->assertTrue($service->getMarker($target)?->isEnqueued());
        $this->assertSame(1700000000123, $service->getMarker($target)?->getQueuedAt());

        $enqueued = $service->getMarker($target);
        $this->assertTrue($service->claim($target, $enqueued));
        $this->assertSame([], $store);
        $this->assertNull($service->getMarker($target));
        $this->assertFalse($service->claim($target, $enqueued), 'Claimed once; a second worker gets nothing');
    }

    public function testANewerRebuildReplacesTheMarkerAndTheOlderOneCanNeitherAdvanceNorClaimIt(): void
    {
        $store = [];
        $target = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);
        $older = $this->createService($store, 1)->markEnqueuing($target);
        $newer = $this->createService($store, 2)->markEnqueuing($target);
        $service = $this->createService($store, 3);

        $this->assertFalse($service->markEnqueued($target, $older));
        $this->assertFalse($service->claim($target, $older));
        $this->assertSame(2, $service->getMarker($target)?->getQueuedAt(), 'The newer rebuild is untouched');

        $this->assertTrue($service->markEnqueued($target, $newer));
        $this->assertFalse($service->claim($target, $newer), 'The enqueuing marker is gone; the claim needs the enqueued one');
        $this->assertTrue($service->claim($target, $newer->withEnqueued()));
        $this->assertSame([], $store);
    }

    public function testAValueThatIsNoMarkerIsIgnored(): void
    {
        $store = ['asset_rebuild_queued_at' => '1700000000123'];
        $service = $this->createService($store, 1);

        $this->assertNull($service->getMarker(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS)));
        $this->assertNull(IndexRebuildMarker::fromStoredValue('not json'));
        $this->assertNull(IndexRebuildMarker::fromStoredValue('{"queuedAt":"1","enqueued":true}'));
    }

    /**
     * A settings store in an array with the two conditional operations the marker relies on.
     *
     * @param array<string, string> $store
     */
    private function createService(array &$store, int $now): IndexRebuildStateService
    {
        $settingsStore = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'getIndexState' => static function (string $key) use (&$store): ?string {
                return $store[$key] ?? null;
            },
            'storeIndexState' => static function (string $key, string $value) use (&$store): void {
                $store[$key] = $value;
            },
            'replaceIndexState' => static function (string $key, string $expected, string $value) use (&$store): bool {
                if (($store[$key] ?? null) !== $expected) {
                    return false;
                }
                $store[$key] = $value;

                return true;
            },
            'removeIndexStateIfEquals' => static function (string $key, string $expected) use (&$store): bool {
                if (($store[$key] ?? null) !== $expected) {
                    return false;
                }
                unset($store[$key]);

                return true;
            },
        ]);

        return new IndexRebuildStateService(
            $settingsStore,
            $this->makeEmpty(TimeServiceInterface::class, ['getCurrentMillisecondTimestamp' => $now])
        );
    }
}
