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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexQueue;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingService;

/**
 * @internal
 */
final class SynchronousProcessingServiceTest extends Unit
{
    private SynchronousProcessingService $synchronousProcessingService;

    public function _before(): void
    {
        $this->synchronousProcessingService = new SynchronousProcessingService();
    }

    public function testIsDisabledByDefault(): void
    {
        $this->assertFalse($this->synchronousProcessingService->isEnabled());
    }

    public function testEnable(): void
    {
        $this->synchronousProcessingService->enable();
        $this->assertTrue($this->synchronousProcessingService->isEnabled());
    }

    public function testDisable(): void
    {
        $this->testEnable();

        $this->synchronousProcessingService->disable();
        $this->assertFalse($this->synchronousProcessingService->isEnabled());
    }
}
