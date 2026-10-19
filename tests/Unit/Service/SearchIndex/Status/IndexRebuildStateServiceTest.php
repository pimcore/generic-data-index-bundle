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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexRebuildStateService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;

/**
 * @internal
 */
final class IndexRebuildStateServiceTest extends Unit
{
    public function testTheMarkerIsStoredPerIndexWithTheTimestamp(): void
    {
        $store = [];
        $service = $this->createService($store, 1700000000123);
        $target = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);

        $this->assertNull($service->getQueuedAt($target));

        $service->markQueued($target);
        $this->assertSame(['asset_rebuild_queued_at' => '1700000000123'], $store);
        $this->assertSame(1700000000123, $service->getQueuedAt($target));
        $this->assertNull($service->getQueuedAt(IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS)));

        $service->clear($target);
        $this->assertSame([], $store);
        $this->assertNull($service->getQueuedAt($target));
    }

    /**
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
            'removeIndexState' => static function (string $key) use (&$store): void {
                unset($store[$key]);
            },
        ]);

        return new IndexRebuildStateService(
            $settingsStore,
            $this->makeEmpty(TimeServiceInterface::class, ['getCurrentMillisecondTimestamp' => $now])
        );
    }
}
