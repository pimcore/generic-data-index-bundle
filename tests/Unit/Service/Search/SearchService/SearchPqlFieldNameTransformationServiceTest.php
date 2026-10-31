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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchService;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\IndexNameResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;

/**
 * @internal
 */
final class SearchPqlFieldNameTransformationServiceTest extends Unit
{
    /**
     * @throws Exception
     */
    public function testIsKeywordMultiFieldOnlyForTheKeywordMultiFieldOfAField(): void
    {
        $service = $this->getService();
        $search = $this->makeEmpty(SearchInterface::class);

        $this->assertTrue($service->isKeywordMultiField($search, 'standard_fields.color.keyword'));

        // an object property named "keyword" is a regular field, not a multi-field
        $this->assertFalse($service->isKeywordMultiField($search, 'standard_fields.keyword'));
        $this->assertFalse($service->isKeywordMultiField($search, 'standard_fields.tags.keyword'));

        $this->assertFalse($service->isKeywordMultiField($search, 'standard_fields.color'));
        $this->assertFalse($service->isKeywordMultiField($search, 'standard_fields.unknown.keyword'));
        $this->assertFalse($service->isKeywordMultiField($search, 'keyword'));
    }

    /**
     * @throws Exception
     */
    private function getService(): SearchPqlFieldNameTransformationService
    {
        return new SearchPqlFieldNameTransformationService(
            $this->makeEmpty(IndexNameResolverInterface::class, ['resolveIndexName' => 'testindex']),
            $this->makeEmpty(IndexEntityServiceInterface::class, [
                'getByIndexName' => new IndexEntity('test', 'testindex', null),
            ]),
            $this->makeEmpty(CachedSearchIndexMappingServiceInterface::class, [
                'getMapping' => $this->getTestIndexMappings(),
            ]),
            $this->makeEmpty(PqlAdapterInterface::class),
        );
    }

    private function getTestIndexMappings(): array
    {
        return [
            'testindex' => [
                'mappings' => [
                    'properties' => [
                        'standard_fields' => [
                            'properties' => [
                                'color' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 1024,
                                        ],
                                    ],
                                ],
                                'keyword' => [
                                    'type' => 'keyword',
                                ],
                                'tags' => [
                                    'properties' => [
                                        'keyword' => [
                                            'type' => 'text',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
