<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\IdTransformer;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\ImageGalleryTransformer;

/**
 * @internal
 */
final class IdTransformerTest extends Unit
{
    public function testTransformFieldName(): void
    {
        $transformer = new IdTransformer(
            $this->makeEmpty(MappingAnalyzerServiceInterface::class, [
                'fieldPathExists' => function (string $fieldName, array $indexMapping) {
                    return $fieldName === 'asset' || $fieldName === 'asset.id';
                }
            ])
        );

        $this->assertEquals(
            'asset.id',
            $transformer->transformFieldName('asset', [], null)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('asset.id', [], null)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('document', [], null)
        );
    }

    public function testStopPropagation(): void
    {
        $transformer = new IdTransformer(
            $this->createMock(MappingAnalyzerServiceInterface::class)
        );

        $this->assertTrue($transformer->stopPropagation());
    }
}