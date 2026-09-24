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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\ReplayIndexSettings;
use Pimcore\SearchClient\SearchClientInterface;
use RuntimeException;

final class ReplayIndexSettingsTest extends Unit
{
    /** @var array[] putIndexSettings params, in order */
    private array $puts = [];

    private array $currentSettings = [];

    private array $putResponse = ['acknowledged' => true];

    protected function _before(): void
    {
        $this->puts = [];
        $this->currentSettings = [];
        $this->putResponse = ['acknowledged' => true];
    }

    public function testDisableRefreshSetsMinusOneAndTouchesNothingElse(): void
    {
        (new ReplayIndexSettings($this->client()))->disableRefresh('pimcore_data-object_ptcar');

        $this->assertCount(1, $this->puts);
        $this->assertSame('pimcore_data-object_ptcar', $this->puts[0]['index']);
        $this->assertSame(['refresh_interval' => '-1'], $this->puts[0]['body']['index']);
    }

    public function testDisableRefreshReturnsTheConfiguredValue(): void
    {
        $this->currentSettings = ['refresh_interval' => '30s'];

        $this->assertSame('30s', (new ReplayIndexSettings($this->client()))->disableRefresh('pimcore_asset'));
    }

    public function testDisableRefreshReturnsNullWhenTheIndexUsesTheDefault(): void
    {
        $this->assertNull((new ReplayIndexSettings($this->client()))->disableRefresh('pimcore_asset'));
    }

    public function testRestoreRefreshPutsTheConfiguredValueBack(): void
    {
        (new ReplayIndexSettings($this->client()))->restoreRefresh('pimcore_asset', '30s');

        $this->assertSame(['refresh_interval' => '30s'], $this->puts[0]['body']['index']);
    }

    public function testRestoreRefreshResetsToTheEngineDefault(): void
    {
        (new ReplayIndexSettings($this->client()))->restoreRefresh('pimcore_asset', null);

        $body = $this->puts[0]['body']['index'];
        $this->assertArrayHasKey('refresh_interval', $body);
        $this->assertNull($body['refresh_interval'], 'null resets the setting to the engine default');
    }

    public function testANotAcknowledgedSettingsUpdateFailsTheImport(): void
    {
        $this->putResponse = ['acknowledged' => false];

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('not acknowledged');

        (new ReplayIndexSettings($this->client()))->disableRefresh('pimcore_asset');
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

        (new ReplayIndexSettings($client))->disableRefresh('pimcore_asset');
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

                return $this->putResponse;
            },
        ]);
    }
}
