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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\DefaultSearch\Search\DataObject;

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
        $dataObjectSearch
            ->addModifier($searchModifierMock1)
            ->addModifier($searchModifierMock2)
            ->setClassDefinition($classDefinition)
            ->setUser($user);

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
