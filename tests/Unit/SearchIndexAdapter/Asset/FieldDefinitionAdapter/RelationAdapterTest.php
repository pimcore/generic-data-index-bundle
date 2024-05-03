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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\Asset\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter\RelationAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\Asset\Image;

/**
 * @internal
 */
final class RelationAdapterTest extends Unit
{
    public function testGetIndexMapping()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
        );

        $this->assertSame([
            'properties' => [
                'id' => [
                    'type' => 'long',
                ],
                'type' => [
                    'type' => 'keyword',
                ],
            ],
        ], $adapter->getIndexMapping());
    }

    public function testNormalize()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
        );

        $image = new Image();
        $image->setId(1);

        $this->assertSame([
            'type' => 'asset',
            'id' => 1,
        ], $adapter->normalize($image));
    }

    public function testApplySearchFilterWrongMetaDataType(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('asset');

        $filter = new AssetMetaDataFilter('test', 'input', 1);
        $this->expectException(InvalidArgumentException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilterWrongScalarType()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('asset');

        $filter = new AssetMetaDataFilter('test', 'asset', true);
        $this->expectException(InvalidArgumentException::class);
        $adapter->applySearchFilter($filter, new Search());

        $filter = new AssetMetaDataFilter('test', 'asset', ['test']);
        $this->expectException(InvalidArgumentException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilterWrongArrayType()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('asset');

        $filter = new AssetMetaDataFilter('test', 'checkbox', [1]);
        $this->expectException(InvalidArgumentException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilter()
    {

        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new RelationAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('asset');

        $filter = new AssetMetaDataFilter('test', 'asset', 1);
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.default.asset' => 1,
                        ],
                    ],
                ],
            ],
        ], $search->toArray());

        $filter = new AssetMetaDataFilter('test', 'asset', 2, 'en');
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.en.asset' => 2,
                        ],
                    ],
                ],
            ],
        ], $search->toArray());

        $filter = new AssetMetaDataFilter('test', 'asset', [1, 2], 'en');
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'terms' => [
                            'standard_fields.test.en.asset' => [1, 2],
                        ],
                    ],
                ],
            ],
        ], $search->toArray());

        $filter = new AssetMetaDataFilter('test', 'object', 1);
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.default.object' => 1,
                        ],
                    ],
                ],
            ],
        ], $search->toArray());
        $filter = new AssetMetaDataFilter('test', 'document', 1);
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.default.document' => 1,
                        ],
                    ],
                ],
            ],
        ], $search->toArray());
    }
}
