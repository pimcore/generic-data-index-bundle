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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter\KeywordAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class KeywordAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = new KeywordAdapter(
            $searchIndexConfigServiceInterfaceMock,
        );

        $mapping = $adapter->getIndexMapping();
        $this->assertSame([
            'type' => 'keyword',
        ], $mapping);
    }

    public function testApplySearchFilterWrongMetaDataType(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new KeywordAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('select');

        $filter = new AssetMetaDataFilter('test', 'checkbox', 1);
        $this->expectException(InvalidArgumentException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilterWrongType()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new KeywordAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('select');

        $filter = new AssetMetaDataFilter('test', 'select', 1);
        $this->expectException(InvalidArgumentException::class);
        $adapter->applySearchFilter($filter, new Search());

        $filter = new AssetMetaDataFilter('test', 'select', [null]);
        $this->expectException(InvalidArgumentException::class);
        $adapter->applySearchFilter($filter, new Search());
    }

    public function testApplySearchFilter()
    {

        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = (new KeywordAdapter(
            $searchIndexConfigServiceInterfaceMock,
        ))->setType('select');

        $filter = new AssetMetaDataFilter('test', 'select', 'value');
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.default' => 'value',
                        ],
                    ],
                ],
            ],
        ], $search->toArray());

        $filter = new AssetMetaDataFilter('test', 'select', 'value', 'en');
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [
                            'standard_fields.test.en' => 'value',
                        ],
                    ],
                ],
            ],
        ], $search->toArray());

        $filter = new AssetMetaDataFilter('test', 'select', ['value', 'value2'], 'en');
        $search = new Search();
        $adapter->applySearchFilter($filter, $search);

        $this->assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        'terms' => [
                            'standard_fields.test.en' => ['value', 'value2'],
                        ],
                    ],
                ],
            ],
        ], $search->toArray());
    }
}
