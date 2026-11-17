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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SubprocessPipeSupport;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot\Support\DescriptorPressure;

final class SubprocessPipeSupportTest extends Unit
{
    public function testPipesAreUsableInAnOrdinaryProcess(): void
    {
        $this->assertTrue(SubprocessPipeSupport::pipesAreUsable());
    }

    public function testPipesAreNotUsableOnceDescriptorsPassTheSelectLimit(): void
    {
        $pressure = new DescriptorPressure();
        if (!$pressure->raiseAboveSelectLimit()) {
            $pressure->release();
            $this->markTestSkipped('this process may not open more than FD_SETSIZE descriptors');
        }

        try {
            $this->assertFalse(
                SubprocessPipeSupport::pipesAreUsable(),
                'stream_select() cannot be used on a descriptor numbered at or above FD_SETSIZE'
            );
        } finally {
            $pressure->release();
        }

        $this->assertTrue(SubprocessPipeSupport::pipesAreUsable(), 'and is usable again afterwards');
    }
}
