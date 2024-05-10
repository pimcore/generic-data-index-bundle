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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\IdTransformer;

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
