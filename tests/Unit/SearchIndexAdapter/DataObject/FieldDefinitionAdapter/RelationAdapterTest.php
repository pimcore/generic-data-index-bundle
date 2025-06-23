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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\RelationAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation;
use Pimcore\Model\Document\Page;

/**
 * @internal
 */
final class RelationAdapterTest extends Unit
{
    public function testGetSearchIndexMapping()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );
        $relation = new ManyToManyRelation();
        $adapter->setFieldDefinition($relation);

        $this->assertSame([
            'properties' => [
                'object' => [
                    'type' => 'long',
                ],
                'asset' => [
                    'type' => 'long',
                ],
                'document' => [
                    'type' => 'long',
                ],
            ],
        ], $adapter->getIndexMapping());
    }

    public function testNormalize()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );
        $relation = new ManyToManyRelation();
        $adapter->setFieldDefinition($relation);

        $image = new Image();
        $image->setId(1);

        $page = new Page();
        $page->setId(5);

        $this->assertSame([
            'object' => [],
            'asset' => [1],
            'document' => [5],
        ], $adapter->normalize([$image, $page]));
    }

    public function testNormalizeManyToOne()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );
        $relation = new ManyToOneRelation();
        $adapter->setFieldDefinition($relation);

        $image = new Image();
        $image->setId(1);

        $this->assertSame([
            'object' => [],
            'asset' => [1],
            'document' => [],
        ], $adapter->normalize($image));
    }
}
