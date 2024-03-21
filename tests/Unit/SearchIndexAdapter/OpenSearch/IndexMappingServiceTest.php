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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\OpenSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\IndexMappingService;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;

/**
 * @internal
 */
final class IndexMappingServiceTest extends Unit
{
    public function testGetMappingWithEmptyFieldDefinitions(): void
    {
        $fieldDefinitionServiceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $indexMappingService = new IndexMappingService($fieldDefinitionServiceMock);

        $this->assertSame(
            ['properties' => []],
            $indexMappingService->getMappingForFieldDefinitions([])
        );
    }

    public function testGetMappingWhenFieldDefinitionsHasNoName()
    {
        $fieldDefinitionServiceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $indexMappingService = new IndexMappingService($fieldDefinitionServiceMock);

        $input = new Input();

        $this->assertSame(
            ['properties' => []],
            $indexMappingService->getMappingForFieldDefinitions([$input])
        );
    }

    public function testGetMappingWhenFieldDefinitionAdapterIsNotSet()
    {
        $fieldDefinitionServiceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'getFieldDefinitionAdapter' => null,
        ]);
        $indexMappingService = new IndexMappingService($fieldDefinitionServiceMock);

        $input = new Input();
        $input->setName('test');

        $this->assertSame(
            ['properties' => []],
            $indexMappingService->getMappingForFieldDefinitions([$input])
        );
    }

    public function testGetMappingWithCorrectAdapter()
    {
        $adapterMock = $this->makeEmpty(AdapterInterface::class, [
            'getIndexMapping' => ['properties' => ['test' => ['type' => 'text']]],
            'getIndexAttributeName' => 'testIndexName',
        ]);

        $fieldDefinitionServiceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'getFieldDefinitionAdapter' => $adapterMock,
        ]);

        $input = new Input();
        $input->setName('testInput');

        $indexMappingService = new IndexMappingService($fieldDefinitionServiceMock);
        $mapping = $indexMappingService->getMappingForFieldDefinitions([$input]);

        $this->assertSame(
            ['properties' => [
                'testIndexName' => [
                    'properties' => [
                        'test' => ['type' => 'text'],
                    ],
                ],
            ]],
            $mapping
        );
    }

    public function testTransformedLocalizedfields()
    {
        $adapterMock = $this->makeEmpty(AdapterInterface::class, [
            'getIndexMapping' => $this->getLocalizedFieldsMappingMock(),
            'getIndexAttributeName' => 'localizedfields',
        ]);

        $fieldDefinitionServiceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'getFieldDefinitionAdapter' => $adapterMock,
        ]);

        $localizedfields = new Localizedfields();
        $localizedfields->setName('localizedfields');

        $indexMappingService = new IndexMappingService($fieldDefinitionServiceMock);
        $mapping = $indexMappingService->getMappingForFieldDefinitions([$localizedfields]);
        $this->assertSame(
            $this->getTransformedLocalizedFieldsMapping(),
            $mapping
        );
    }

    public function testGetMappingForTextKeywordWithoutArguments(): void
    {
        $fieldDefinitionServiceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $indexMappingService = new IndexMappingService($fieldDefinitionServiceMock);

        $this->assertSame(
            [
                'type' => 'text',
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
            $indexMappingService->getMappingForTextKeyword([])
        );
    }

    public function testGetMappingForTextKeywordWithArguments(): void
    {
        $fieldDefinitionServiceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $indexMappingService = new IndexMappingService($fieldDefinitionServiceMock);
        $attributes = [
            'text' => [
                'fields' => [
                    'analyzed_ngram' => [
                        'type' => 'text',
                        'analyzer' => 'ngram_analyzer',
                        'search_analyzer' => 'generic_data_index_whitespace_analyzer',
                    ],
                ],
            ],
        ];
        $keyWordMapping = $indexMappingService->getMappingForTextKeyword($attributes);

        $this->assertCount(2, $keyWordMapping['fields']);
        $this->assertSame(
            [
                'type' => 'text',
                'fields' => [
                    'analyzed_ngram' => [
                        'type' => 'text',
                        'analyzer' => 'ngram_analyzer',
                        'search_analyzer' => 'generic_data_index_whitespace_analyzer',
                    ],
                    'keyword' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
            $keyWordMapping
        );
    }

    private function getLocalizedFieldsMappingMock(): array
    {
        return [
            'properties' => [
                'de' => [
                    'properties' => [
                        'input1' => [
                            'type' => 'text',
                        ],
                        'input2' => [
                            'type' => 'text',
                        ],
                    ],
                ],
                'en' => [
                    'properties' => [
                        'input1' => [
                            'type' => 'text',
                        ],
                        'input2' => [
                            'type' => 'text',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getTransformedLocalizedFieldsMapping(): array
    {
        return [
            'properties' => [
                'input1' => [
                    'type' => 'object',
                    'properties' => [
                        'de' => [
                            'type' => 'text',
                        ],
                        'en' => [
                            'type' => 'text',
                        ],
                    ],
                ],
                'input2' => [
                    'type' => 'object',
                    'properties' => [
                        'de' => [
                            'type' => 'text',
                        ],
                        'en' => [
                            'type' => 'text',
                        ],
                    ],
                ],
            ],
        ];
    }
}
