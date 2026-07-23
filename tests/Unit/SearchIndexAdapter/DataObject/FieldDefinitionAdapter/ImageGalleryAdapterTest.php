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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ImageGalleryAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\ImageGallery;

/**
 * @internal
 */
final class ImageGalleryAdapterTest extends Unit
{
    public function testGetSearchIndexMapping(): void
    {
        $searchIndexConfigService = $this->makeEmpty(
            SearchIndexConfigServiceInterface::class,
            [
                'getSearchAnalyzerAttributes' => [],
            ]
        );

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        $indexMappingService = $this->makeEmpty(
            IndexMappingServiceInterface::class,
            [
                'getMappingForAdvancedImage' => [],
            ]
        );

        $adapter = new ImageGalleryAdapter(
            $searchIndexConfigService,
            $fieldDefinitionService,
            $indexMappingService
        );

        $gallery = new ImageGallery();
        $adapter->setFieldDefinition($gallery);

        $this->assertSame([
            'properties' => [
                'assets' => [
                    'type' => AttributeType::LONG->value,
                ],
                'details' => [],
            ],
        ], $adapter->getIndexMapping());
    }

    public function testNormalizeCollectsAssetIdsAndSkipsGalleryItemsWithoutImageId(): void
    {
        $searchIndexConfigService = $this->makeEmpty(
            SearchIndexConfigServiceInterface::class
        );

        $fieldDefinitionService = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        $indexMappingService = $this->makeEmpty(
            IndexMappingServiceInterface::class
        );

        $adapter = new ImageGalleryAdapter(
            $searchIndexConfigService,
            $fieldDefinitionService,
            $indexMappingService
        );

        $gallery = new class extends ImageGallery {
            public function normalize(mixed $value, array $params = []): array 
            {
                return [
                    [
                        'image' => [
                            'id' => 123,
                        ],
                        'hotspots' => [],
                    ],
                    [
                        'image' => [],
                        'hotspots' => [],
                    ],
                ];
            }
        };

        $adapter->setFieldDefinition($gallery);

        $result = $adapter->normalize([]);

        $this->assertSame(
            [
                123,
            ],
            $result['assets']
        );

        $this->assertSame(
            [
                [
                    'image' => [
                        'id' => 123,
                    ],
                    'hotspots' => [],
                ],
                [
                    'image' => [],
                    'hotspots' => [],
                ],
            ],
            $result['details']
        );
    }
}