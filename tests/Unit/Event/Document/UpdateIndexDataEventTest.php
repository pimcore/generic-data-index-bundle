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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Document;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Document\UpdateIndexDataEvent;
use Pimcore\Model\Document;

/**
 * @internal
 */
final class UpdateIndexDataEventTest extends Unit
{
    public function testGetElement(): void
    {
        $documentMock = $this->createMock(Document::class);
        $event = new UpdateIndexDataEvent($documentMock, ['test' => 'test']);
        $this->assertEquals($documentMock, $event->getElement());
    }

    public function testGetCustomFields(): void
    {
        $documentMock = $this->createMock(Document::class);
        $event = new UpdateIndexDataEvent($documentMock, ['test' => 'test']);
        $this->assertEquals(['test' => 'test'], $event->getCustomFields());
    }

    public function testSetCustomFields(): void
    {
        $documentMock = $this->createMock(Document::class);
        $event = new UpdateIndexDataEvent($documentMock, ['test' => 'test']);
        $event->setCustomFields(['test2' => 'test2']);
        $this->assertEquals(['test2' => 'test2'], $event->getCustomFields());
    }
}
