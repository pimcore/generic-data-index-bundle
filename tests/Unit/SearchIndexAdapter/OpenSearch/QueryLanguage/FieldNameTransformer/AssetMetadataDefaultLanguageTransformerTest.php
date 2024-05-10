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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\MappingAnalyzerServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\AssetMetadataDefaultLanguageTransformer;

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
