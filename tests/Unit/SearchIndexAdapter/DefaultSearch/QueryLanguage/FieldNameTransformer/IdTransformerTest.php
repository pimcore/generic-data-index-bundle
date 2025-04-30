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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameTransformer\IdTransformer;

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
                },
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
