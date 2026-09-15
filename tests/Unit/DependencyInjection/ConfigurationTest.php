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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends Unit
{
    public function testSnapshotDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertSame([
            'storage' => 'pimcore.generic_data_index_snapshot.storage',
            'keep' => 3,
            'page_size' => 1000,
            'bulk_size' => 1000,
        ], $config['snapshot']);
    }

    public function testSnapshotOverrides(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'snapshot' => ['storage' => 'pimcore.customer_snapshots.storage', 'keep' => 0, 'page_size' => 250, 'bulk_size' => 500],
        ]]);

        $this->assertSame('pimcore.customer_snapshots.storage', $config['snapshot']['storage']);
        $this->assertSame(0, $config['snapshot']['keep']);
        $this->assertSame(250, $config['snapshot']['page_size']);
    }

    public function testNegativeKeepIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['snapshot' => ['keep' => -1]]]);
    }
}
