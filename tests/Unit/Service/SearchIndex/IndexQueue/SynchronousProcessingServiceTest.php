<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
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