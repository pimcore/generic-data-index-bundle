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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameTransformer\AssetMetadataDefaultLanguageTransformer;

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
                },
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
                },
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
