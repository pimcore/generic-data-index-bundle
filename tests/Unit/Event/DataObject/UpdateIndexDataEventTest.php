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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\DataObject;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\UpdateIndexDataEvent;
use Pimcore\Model\DataObject\Concrete;

/**
 * @internal
 */
final class UpdateIndexDataEventTest extends Unit
{
    public function testGetElement(): void
    {
        $assetMock = $this->createMock(Concrete::class);
        $event = new UpdateIndexDataEvent($assetMock, ['test' => 'test']);
        $this->assertEquals($assetMock, $event->getElement());
    }

    public function testGetCustomFields(): void
    {
        $assetMock = $this->createMock(Concrete::class);
        $event = new UpdateIndexDataEvent($assetMock, ['test' => 'test']);
        $this->assertEquals(['test' => 'test'], $event->getCustomFields());
    }

    public function testSetCustomFields(): void
    {
        $assetMock = $this->createMock(Concrete::class);
        $event = new UpdateIndexDataEvent($assetMock, ['test' => 'test']);
        $event->setCustomFields(['test2' => 'test2']);
        $this->assertEquals(['test2' => 'test2'], $event->getCustomFields());
    }
}
