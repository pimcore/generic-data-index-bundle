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

    private array $currentSettings = [];

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
        $this->currentSettings = [];
    }

    private function client(): SearchClientInterface
    {
        return $this->makeEmpty(SearchClientInterface::class, [
            'getIndexSettings' => fn (array $params): array => [
                $params['index'] . '-odd' => ['settings' => ['index' => $this->currentSettings + ['number_of_shards' => '1']]],
            ],
            'putIndexSettings' => function (array $params): array {
                $this->puts[] = $params;

                return ['acknowledged' => true];
            },
        ]);
    }
}
