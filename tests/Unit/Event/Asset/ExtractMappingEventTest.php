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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Asset;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\ExtractMappingEvent;

/**
 * @internal
 */
final class ExtractMappingEventTest extends Unit
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
