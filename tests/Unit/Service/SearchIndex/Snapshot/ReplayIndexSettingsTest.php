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
    public function testApplyDisablesRefreshAndMakesTheTranslogAsynchronous(): void
    {
        $calls = [];
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'putIndexSettings' => static function (array $params) use (&$calls): array {
                $calls[] = $params;

                return ['acknowledged' => true];
            },
        ]);

        (new ReplayIndexSettings($client))->apply('pimcore_data-object_ptcar');

        $this->assertCount(1, $calls);
        $this->assertSame('pimcore_data-object_ptcar', $calls[0]['index']);
        $this->assertSame('-1', $calls[0]['body']['index']['refresh_interval']);
        $this->assertSame('async', $calls[0]['body']['index']['translog']['durability']);
    }

    public function testRestoreResetsBothSettingsToTheirDefaults(): void
    {
        $calls = [];
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'putIndexSettings' => static function (array $params) use (&$calls): array {
                $calls[] = $params;

                return ['acknowledged' => true];
            },
        ]);

        (new ReplayIndexSettings($client))->restore('pimcore_data-object_ptcar');

        $this->assertCount(1, $calls);
        $body = $calls[0]['body']['index'];
        $this->assertArrayHasKey('refresh_interval', $body);
        $this->assertNull($body['refresh_interval'], 'null resets the setting to the engine default');
        $this->assertNull($body['translog']['durability']);
    }

    public function testClientFailuresSurfaceAsSnapshotImportException(): void
    {
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'putIndexSettings' => static function (): never {
                throw new RuntimeException('settings boom');
            },
        ]);

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('settings boom');

        (new ReplayIndexSettings($client))->apply('pimcore_asset');
    }
}
