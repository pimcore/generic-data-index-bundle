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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Search\DataObject;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\User;

/**
 * @internal
 */
final class DataObjectSearchTest extends Unit
{
    public function testDataObjectSearch()
    {
        $searchModifierMock1 = $this->makeEmpty(SearchModifierInterface::class);
        $searchModifierMock2 = $this->makeEmpty(SearchModifierInterface::class);
        $user = new User();
        $user->setId(1);
        $classDefinition = new ClassDefinition();
        $classDefinition->setId('testClass');
        $classDefinition->setName('testClassDefinition');

        $dataObjectSearch = new DataObjectSearch();
        $dataObjectSearch->addModifier($searchModifierMock1);
        $dataObjectSearch->addModifier($searchModifierMock2);
        $dataObjectSearch->setUser($user);
        $dataObjectSearch->setClassDefinition($classDefinition);

        $this->assertCount(2, $dataObjectSearch->getModifiers());
        $this->assertSame([
            $searchModifierMock1,
            $searchModifierMock2,
        ], $dataObjectSearch->getModifiers());
        $this->assertSame(1, $dataObjectSearch->getUser()->getId());
        $this->assertSame('testClass', $dataObjectSearch->getClassDefinition()->getId());
        $this->assertSame('testClassDefinition', $dataObjectSearch->getClassDefinition()->getName());
    }
}
