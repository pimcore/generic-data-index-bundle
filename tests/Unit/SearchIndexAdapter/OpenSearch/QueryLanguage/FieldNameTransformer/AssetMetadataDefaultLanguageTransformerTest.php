<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\AssetMetadataDefaultLanguageTransformer;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\IdTransformer;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\ImageGalleryTransformer;

/**
 * @internal
 */
final class AssetMetadataDefaultLanguageTransformerTest extends Unit
{
    public function testTransformFieldName(): void
    {
        $transformer = new AssetMetadataDefaultLanguageTransformer(
            $this->makeEmpty(MappingAnalyzerServiceInterface::class, [
                'fieldPathExists' => function (string $fieldName, array $indexMapping) {
                    return in_array($fieldName, ['system_fields.fileSize', 'metaData', 'metaData.default', 'metaData.en', 'metaData.de']);
                }
            ])
        );

        $this->assertEquals(
            'metaData.default',
            $transformer->transformFieldName('metaData', [], null)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('metaData.en', [], null)
        );
        $this->assertEquals(
            null,
            $transformer->transformFieldName('metaData.de', [], null)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('foo', [], null)
        );


        //test in not asset index
        $transformer = new AssetMetadataDefaultLanguageTransformer(
            $this->makeEmpty(MappingAnalyzerServiceInterface::class, [
                'fieldPathExists' => function (string $fieldName, array $indexMapping) {
                    return in_array($fieldName, ['metaData', 'metaData.default', 'metaData.en', 'metaData.de']);
                }
            ])
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('metaData', [], null)
        );
    }

    public function testStopPropagation(): void
    {
        $transformer = new AssetMetadataDefaultLanguageTransformer(
            $this->createMock(MappingAnalyzerServiceInterface::class)
        );

        $this->assertFalse($transformer->stopPropagation());
    }
}