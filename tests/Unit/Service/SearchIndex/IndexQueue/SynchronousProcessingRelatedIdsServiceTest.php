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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingRelatedIdsService;

/**
 * @internal
 */
final class SynchronousProcessingRelatedIdsServiceTest extends Unit
{
    private SynchronousProcessingRelatedIdsService $synchronousProcessingRelatedIdsService;

    public function _before(): void
    {
        $this->synchronousProcessingRelatedIdsService = new SynchronousProcessingRelatedIdsService();
    }

    public function testIsDisabledByDefault(): void
    {
        $this->assertFalse($this->synchronousProcessingRelatedIdsService->isEnabled());
    }

    public function testEnable(): void
    {
        $this->synchronousProcessingRelatedIdsService->enable();
        $this->assertTrue($this->synchronousProcessingRelatedIdsService->isEnabled());
    }

    public function testDisable(): void
    {
        $this->testEnable();

        $this->synchronousProcessingRelatedIdsService->disable();
        $this->assertFalse($this->synchronousProcessingRelatedIdsService->isEnabled());
    }
}
