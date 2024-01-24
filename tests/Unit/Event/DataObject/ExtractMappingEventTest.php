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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\DataObject;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\ExtractMappingEvent;
use Pimcore\Model\DataObject\ClassDefinitionInterface;

class ExtractMappingEventTest extends Unit
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
