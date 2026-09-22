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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\DependencyInjection;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends Unit
{
    public function testSnapshotDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertSame([
            'storage' => 'pimcore.generic_data_index_snapshot.storage',
            'page_size' => 1000,
            'page_bytes' => 16 * 1024 * 1024,
            'bulk_size' => 1000,
            'import_workers' => 4,
            'bulk_bytes' => 16 * 1024 * 1024,
        ], $config['snapshot']);
    }

    public function testSnapshotOverrides(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'snapshot' => [
                'storage' => 'pimcore.customer_snapshots.storage',
                'page_size' => 250,
                'page_bytes' => 4096,
                'bulk_size' => 500,
                'bulk_bytes' => 8192,
                'import_workers' => 1,
            ],
        ]]);

        $this->assertSame('pimcore.customer_snapshots.storage', $config['snapshot']['storage']);
        $this->assertSame(250, $config['snapshot']['page_size']);
        $this->assertSame(4096, $config['snapshot']['page_bytes']);
        $this->assertSame(500, $config['snapshot']['bulk_size']);
        $this->assertSame(8192, $config['snapshot']['bulk_bytes']);
        $this->assertSame(1, $config['snapshot']['import_workers']);
    }
}
