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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter\FieldCollectionAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\FieldCollection\DefinitionResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Pimcore\Model\DataObject\Fieldcollection;

class FieldCollectionAdapterTest extends Unit
{

    public function testOpenSearchMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $definitionResolverMock = $this->makeEmpty(DefinitionResolverInterface::class, [
            'getByKey' => $this->makeEmpty(Fieldcollection::class)
        ]);
        $adapter = new FieldCollectionAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $fieldCollection = new Fieldcollections();
        $fieldCollection->setAllowedTypes(['my-type']);
        $adapter->setFieldDefinition($fieldCollection);
        $adapter->setFieldCollectionDefinitionResolver($definitionResolverMock);
        $mapping = $adapter->getOpenSearchMapping();

        $this->assertSame([
                'type' => AttributeType::NESTED,
                'properties' => [
                    'type' => [
                        'type' => AttributeType::TEXT,
                    ]
                ],
            ], $mapping
        );
        
    }

    public function testExceptionIsThrownWhenFieldDefinitionIsNotSet(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new FieldCollectionAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $relation = new Checkbox();
        $adapter->setFieldDefinition($relation);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FieldDefinition must be of type Fieldcollections');
        $adapter->getOpenSearchMapping();
    }
}