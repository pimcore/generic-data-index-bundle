<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
