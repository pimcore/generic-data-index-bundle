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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter\VideoAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Video;

/**
 * @internal
 */
final class VideoAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        $adapter = new VideoAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $video = new Video();
        $adapter->setFieldDefinition($video);
        $this->assertSame([
            'properties' => [
                'id' => [
                    'type' => AttributeType::TEXT,
                ],
                'type' => [
                    'type' => AttributeType::KEYWORD,
                ],
                'details' => [
                    'type' => AttributeType::NESTED,
                    'properties' => [
                        'type' => [
                            'type' => AttributeType::KEYWORD,
                        ],
                        'title' => [
                            'type' => AttributeType::KEYWORD,
                        ],
                        'description' => [
                            'type' => AttributeType::KEYWORD,
                        ],
                        'poster' => [
                            'properties' => [
                                'id' => [
                                    'type' => AttributeType::LONG,
                                ],
                                'type' => [
                                    'type' => AttributeType::KEYWORD,
                                ],
                            ],
                        ],
                        'data' => [
                            'properties' => [
                                'id' => [
                                    'type' => AttributeType::LONG,
                                ],
                                'type' => [
                                    'type' => AttributeType::KEYWORD,
                                ],
                            ],
                        ],
                    ]
                ]
            ]
        ], $adapter->getIndexMapping());
    }

    public function testNormalizeAsset(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        $adapter = new VideoAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $video = new Video();
        $adapter->setFieldDefinition($video);

        $videoAsset = new Asset\Video();
        $videoAsset->setId(1);
        $videoPoster = new Asset\Image();
        $videoPoster->setId(2);

        $videoData = new DataObject\Data\Video();
        $videoData->setType('asset');
        $videoData->setTitle('video title');
        $videoData->setDescription('video description');
        $videoData->setPoster($videoPoster);
        $videoData->setData($videoAsset);
        $normalizedData = $adapter->normalize($videoData);

        $this->assertSame('asset', $normalizedData['type']);
        $this->assertSame(1, $normalizedData['id']);
        $this->assertSame([
            'type' => 'asset',
            'title' => 'video title',
            'description' => 'video description',
            'poster' => [
                'type' => 'asset',
                'id' => 2,
            ],
            'data' => [
                'type' => 'asset',
                'id' => 1,
            ],
        ], $normalizedData['details']);
    }

    public function testNormalizeExternal(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        $adapter = new VideoAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $video = new Video();
        $adapter->setFieldDefinition($video);

        $videoData = new DataObject\Data\Video();
        $videoData->setType('youtube');
        $videoData->setData('youtubeIdCode');
        $normalizedData = $adapter->normalize($videoData);

        $this->assertSame('youtube', $normalizedData['type']);
        $this->assertSame('youtubeIdCode', $normalizedData['id']);
        $this->assertNull($normalizedData['details']);
    }
}
