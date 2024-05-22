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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\FieldCategoryTransformer;

/**
 * @internal
 */
final class FieldCategoryTransformerTest extends Unit
{
    public function testTransformFieldName(): void
    {
        $transformer = new FieldCategoryTransformer(
            $this->makeEmpty(MappingAnalyzerServiceInterface::class, [
                'fieldPathExists' => function (string $fieldName, array $indexMapping) {
                    return $fieldName === 'system_fields.id' || $fieldName === 'standard_fields.series';
                },
            ])
        );

        $this->assertEquals(
            'system_fields.id',
            $transformer->transformFieldName('id', [], null)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('system_fields.id', [], null)
        );

        $this->assertEquals(
            'standard_fields.series',
            $transformer->transformFieldName('series', [], null)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('foo', [], null)
        );
    }

    public function testStopPropagation(): void
    {
        $transformer = new FieldCategoryTransformer(
            $this->createMock(MappingAnalyzerServiceInterface::class)
        );

        $this->assertFalse($transformer->stopPropagation());
    }
}
