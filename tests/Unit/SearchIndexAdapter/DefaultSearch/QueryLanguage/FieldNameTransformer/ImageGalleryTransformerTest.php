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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameTransformer;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameTransformer\ImageGalleryTransformer;

/**
 * @internal
 */
final class ImageGalleryTransformerTest extends Unit
{
    public function testTransformFieldName(): void
    {
        $transformer = new ImageGalleryTransformer(
            $this->makeEmpty(MappingAnalyzerServiceInterface::class, [
                'fieldPathExists' => function (string $fieldName, array $indexMapping) {
                    return $fieldName === 'standard_fields.gallery' || $fieldName === 'standard_fields.gallery.assets';
                },
            ])
        );

        $assetIndexEntity = new IndexEntity('assets', 'assets', IndexType::ASSET);

        $this->assertEquals(
            'standard_fields.gallery.assets',
            $transformer->transformFieldName('standard_fields.gallery', [], $assetIndexEntity)
        );
        $this->assertEquals(
            null,
            $transformer->transformFieldName('standard_fields.gallery.assets', [], $assetIndexEntity)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('gallery', [], $assetIndexEntity)
        );
    }

    public function testStopPropagation(): void
    {
        $transformer = new ImageGalleryTransformer(
            $this->createMock(MappingAnalyzerServiceInterface::class)
        );

        $this->assertTrue($transformer->stopPropagation());
    }
}
