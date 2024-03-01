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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidValueException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter\BooleanAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class BooleanAdapterTest extends Unit
{
    public function testGetIndexMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = new BooleanAdapter(
            $searchIndexConfigServiceInterfaceMock,
        );

        $mapping = $adapter->getIndexMapping();
        $this->assertSame([
            'type' => 'boolean',
        ], $mapping);
    }

    public function testApplySearchFilterWrongMetaDataType(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new BooleanAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('checkbox');

        $filter = new AssetMetaDataFilter('test', 'input', 1);
        $this->expectException(InvalidValueException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilterWrongScalarType()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new BooleanAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('checkbox');

        $filter = new AssetMetaDataFilter('test', 'checkbox', 1);
        $this->expectException(InvalidValueException::class);
        $adapter->applySearchFilter($filter, new Search());

        $filter = new AssetMetaDataFilter('test', 'checkbox', ['test']);
        $this->expectException(InvalidValueException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilterWrongArrayType()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new BooleanAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('checkbox');

        $filter = new AssetMetaDataFilter('test', 'checkbox', [1]);
        $this->expectException(InvalidValueException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilter()
    {

        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new BooleanAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('checkbox');

        $filter = new AssetMetaDataFilter('test', 'checkbox', true);
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.default' => true,
                        ],
                    ],
                ],
            ],
        ], $search->toArray());

        $filter = new AssetMetaDataFilter('test', 'checkbox', false, 'en');
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.en' => false,
                        ],
                    ],
                ],
            ],
        ], $search->toArray());

        $filter = new AssetMetaDataFilter('test', 'checkbox', [true, false], 'en');
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'terms' => [
                            'standard_fields.test.en' => [true, false],
                        ],
                    ],
                ],
            ],
        ], $search->toArray());
    }
}
