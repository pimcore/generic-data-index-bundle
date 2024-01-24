<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Asset;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\UpdateIndexDataEvent;
use Pimcore\Model\Asset;

class UpdateIndexDataEventTest extends Unit
{
    public function testGetElement(): void
    {
        $assetMock = $this->createMock(Asset::class);
        $event = new UpdateIndexDataEvent($assetMock, ['test' => 'test']);
        $this->assertEquals($assetMock, $event->getElement());
    }

    public function testGetCustomFields(): void
    {
        $assetMock = $this->createMock(Asset::class);
        $event = new UpdateIndexDataEvent($assetMock, ['test' => 'test']);
        $this->assertEquals(['test' => 'test'], $event->getCustomFields());
    }

    public function testSetCustomFields() : void
    {
        $assetMock = $this->createMock(Asset::class);
        $event = new UpdateIndexDataEvent($assetMock, ['test' => 'test']);
        $event->setCustomFields(['test2' => 'test2']);
        $this->assertEquals(['test2' => 'test2'], $event->getCustomFields());
    }

}