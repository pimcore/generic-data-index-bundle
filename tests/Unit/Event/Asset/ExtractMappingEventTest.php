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
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;

class ExtractMappingEventTest extends Unit
{
    public function testGetCustomFieldsMapping(): void
    {
        $event = new ExtractMappingEvent(['test']);
        $this->assertSame(['test'], $event->getCustomFieldsMapping());
    }

    public function testSetCustomFieldsMapping(): void
    {
        $event = new ExtractMappingEvent(['test']);
        $event->setCustomFieldsMapping(['test2']);
        $this->assertSame(['test2'], $event->getCustomFieldsMapping());
    }
}