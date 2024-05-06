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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\DataObject;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\ExtractMappingEvent;
use Pimcore\Model\DataObject\ClassDefinitionInterface;

/**
 * @internal
 */
final class ExtractMappingEventTest extends Unit
{
    public function testGetCustomFieldsMapping(): void
    {
        $classDefinition = $this->createMock(ClassDefinitionInterface::class);
        $event = new ExtractMappingEvent($classDefinition, ['test']);
        $this->assertSame(['test'], $event->getCustomFieldsMapping());
    }

    public function testSetCustomFieldsMapping(): void
    {
        $classDefinition = $this->createMock(ClassDefinitionInterface::class);
        $event = new ExtractMappingEvent($classDefinition, ['test']);
        $event->setCustomFieldsMapping(['test2']);
        $this->assertSame(['test2'], $event->getCustomFieldsMapping());
    }

    public function testGetClassDefinition(): void
    {
        $classDefinition = $this->createMock(ClassDefinitionInterface::class);
        $event = new ExtractMappingEvent($classDefinition, ['test']);
        $this->assertSame($classDefinition, $event->getClassDefinition());
    }
}
