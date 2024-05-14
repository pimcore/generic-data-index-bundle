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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerService;

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
                                            'ignore_above' => 256
                                        ]
                                    ]
                                ],
                                'fieldB' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 256
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'standard_fields' => [
                            'properties' => [
                                'field1' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 256
                                        ]
                                    ]
                                ],
                                'field2' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'keyword' => [
                                            'type' => 'keyword',
                                            'ignore_above' => 256
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}
