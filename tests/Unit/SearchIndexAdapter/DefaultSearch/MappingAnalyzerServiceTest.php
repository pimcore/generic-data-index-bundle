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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\MappingAnalyzerService;

/**
 * @internal
 */
final class MappingAnalyzerServiceTest extends Unit
{
    public function testFieldPathExists(): void
    {
        $mappingAnalyzerService = new MappingAnalyzerService();

        $this->assertTrue($mappingAnalyzerService->fieldPathExists('system_fields', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('system_fields.fieldA', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('system_fields.fieldA.keyword', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('system_fields', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('system_fields.fieldB', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('system_fields.fieldB.keyword', $this->getTestIndexMappings()));

        $this->assertTrue($mappingAnalyzerService->fieldPathExists('standard_fields', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('standard_fields.field1', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('standard_fields.field1.keyword', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('standard_fields', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('standard_fields.field2', $this->getTestIndexMappings()));
        $this->assertTrue($mappingAnalyzerService->fieldPathExists('standard_fields.field2.keyword', $this->getTestIndexMappings()));

        $this->assertFalse($mappingAnalyzerService->fieldPathExists('test_fields', $this->getTestIndexMappings()));
        $this->assertFalse($mappingAnalyzerService->fieldPathExists('standard_fields.field007', $this->getTestIndexMappings()));
        $this->assertFalse($mappingAnalyzerService->fieldPathExists('standard_fields.field1.keyword.test', $this->getTestIndexMappings()));
        $this->assertFalse($mappingAnalyzerService->fieldPathExists('standard_fields.field1.test', $this->getTestIndexMappings()));
    }

    private function getTestIndexMappings(): array
    {
        return [
            'testindex' => [
                'mappings' => [
                    'properties' => [
                        'system_fields' => [
                            'properties' => [
                                'fieldA' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                    ],
                                ],
                                'fieldB' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'standard_fields' => [
                            'properties' => [
                                'field1' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 256,
                                        ],
                                    ],
                                ],
                                'field2' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 256,
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
