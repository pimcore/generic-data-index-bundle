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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\ImageGalleryTransformer;

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
