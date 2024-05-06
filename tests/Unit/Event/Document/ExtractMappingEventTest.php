<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Document;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Document\ExtractMappingEvent;

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
