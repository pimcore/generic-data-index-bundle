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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexSettingsBackup;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\ReplayIndexSettings;
use Pimcore\SearchClient\SearchClientInterface;
use RuntimeException;

final class ReplayIndexSettingsTest extends Unit
{
    /** @var array[] putIndexSettings params, in order */
    private array $puts = [];

    /** @var string[] client calls in order: "flush <index>" / "put <index>" */
    private array $calls = [];

    private array $currentSettings = [];

    private array $flushResponse = ['_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0]];

    public function testApplyDisablesRefreshAndMakesTheTranslogAsynchronous(): void
    {
        (new ReplayIndexSettings($this->client()))->apply('pimcore_data-object_ptcar');

        $this->assertCount(1, $this->puts);
        $this->assertSame('pimcore_data-object_ptcar', $this->puts[0]['index']);
        $this->assertSame('-1', $this->puts[0]['body']['index']['refresh_interval']);
        $this->assertSame('async', $this->puts[0]['body']['index']['translog']['durability']);
    }

    public function testApplyReturnsTheConfiguredValuesTheIndexHadBefore(): void
    {
        // an installation may configure its own values via index_settings; they must survive
        $this->currentSettings = ['refresh_interval' => '30s', 'translog' => ['durability' => 'request']];

        $backup = (new ReplayIndexSettings($this->client()))->apply('pimcore_asset');

        $this->assertSame('30s', $backup->refreshInterval);
        $this->assertSame('request', $backup->translogDurability);
    }

    public function testApplyReportsAbsentSettingsAsNull(): void
    {
        $backup = (new ReplayIndexSettings($this->client()))->apply('pimcore_asset');

        $this->assertNull($backup->refreshInterval);
        $this->assertNull($backup->translogDurability);
    }

    public function testRestorePutsTheBackedUpValuesBack(): void
    {
        (new ReplayIndexSettings($this->client()))->restore('pimcore_asset', new IndexSettingsBackup('30s', 'request'));

        $this->assertSame('30s', $this->puts[0]['body']['index']['refresh_interval']);
        $this->assertSame('request', $this->puts[0]['body']['index']['translog']['durability']);
    }

    public function testRestoreFlushesTheIndexBeforeLeavingBulkLoadingMode(): void
    {
        // With an asynchronous translog a bulk request is acknowledged before it is fsynced.
        // The flush is the durability barrier: only after it may the replay count as complete
        // and the class checksum be stamped.
        (new ReplayIndexSettings($this->client()))->restore('pimcore_asset', new IndexSettingsBackup(null, null));

        $this->assertSame(['flush pimcore_asset', 'put pimcore_asset'], $this->calls);
    }

    public function testAFlushWithFailedShardsFailsTheRestore(): void
    {
        // a normal flush response can still report shards on which the flush did not happen;
        // the durability barrier then did not hold and the replay must not count as complete
        $this->flushResponse = ['_shards' => ['total' => 2, 'successful' => 1, 'failed' => 1]];

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('1 shard(s) failed');

        (new ReplayIndexSettings($this->client()))->restore('pimcore_asset', new IndexSettingsBackup(null, null));
    }

    public function testAFailedFlushStillResetsTheSettings(): void
    {
        $this->flushResponse = ['_shards' => ['total' => 2, 'successful' => 1, 'failed' => 1]];

        try {
            (new ReplayIndexSettings($this->client()))->restore('pimcore_asset', new IndexSettingsBackup('30s', null));
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('1 shard(s) failed', $e->getMessage());
        }
        $this->assertSame(['flush pimcore_asset', 'put pimcore_asset'], $this->calls, 'the index must not stay in bulk mode');
        $this->assertSame('30s', $this->puts[0]['body']['index']['refresh_interval']);
    }

    public function testBothErrorsAreReportedWhenTheResetAfterAFailedFlushFailsToo(): void
    {
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'flushIndex' => static function (): never {
                throw new RuntimeException('flush boom');
            },
            'putIndexSettings' => static function (): never {
                throw new RuntimeException('reset boom');
            },
        ]);

        try {
            (new ReplayIndexSettings($client))->restore('pimcore_asset', new IndexSettingsBackup(null, null));
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('flush boom', $e->getMessage());
            $this->assertStringContainsString('reset boom', $e->getMessage());
        }
    }

    public function testApplyDoesNotFlush(): void
    {
        (new ReplayIndexSettings($this->client()))->apply('pimcore_asset');

        $this->assertSame(['put pimcore_asset'], $this->calls);
    }

    public function testRestoreResetsAbsentSettingsToTheEngineDefaults(): void
    {
        (new ReplayIndexSettings($this->client()))->restore('pimcore_asset', new IndexSettingsBackup(null, null));

        $body = $this->puts[0]['body']['index'];
        $this->assertArrayHasKey('refresh_interval', $body);
        $this->assertNull($body['refresh_interval'], 'null resets the setting to the engine default');
        $this->assertNull($body['translog']['durability']);
    }

    public function testClientFailuresSurfaceAsSnapshotImportException(): void
    {
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'getIndexSettings' => static fn (): array => [],
            'putIndexSettings' => static function (): never {
                throw new RuntimeException('settings boom');
            },
        ]);

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('settings boom');

        (new ReplayIndexSettings($client))->apply('pimcore_asset');
    }

    protected function _before(): void
    {
        $this->puts = [];
        $this->calls = [];
        $this->currentSettings = [];
        $this->flushResponse = ['_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0]];
    }

    private function client(): SearchClientInterface
    {
        return $this->makeEmpty(SearchClientInterface::class, [
            'getIndexSettings' => fn (array $params): array => [
                $params['index'] . '-odd' => [
                    'settings' => ['index' => $this->currentSettings + ['number_of_shards' => '1']],
                ],
            ],
            'putIndexSettings' => function (array $params): array {
                $this->puts[] = $params;
                $this->calls[] = 'put ' . $params['index'];

                return ['acknowledged' => true];
            },
            'flushIndex' => function (array $params): array {
                $this->calls[] = 'flush ' . $params['index'];

                return $this->flushResponse;
            },
        ]);
    }
}
