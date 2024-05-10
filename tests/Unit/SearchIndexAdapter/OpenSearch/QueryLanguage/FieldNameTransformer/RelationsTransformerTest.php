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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\RelationsTransformer;

/**
 * @internal
 */
final class RelationsTransformerTest extends Unit
{
    public function testTransformFieldName(): void
    {
        $transformer = new RelationsTransformer(
            $this->makeEmpty(MappingAnalyzerServiceInterface::class, [
                'fieldPathExists' => function (string $fieldName, array $indexMapping) {
                    return in_array($fieldName, ['relation', 'relation.object', 'relation.document', 'relation.asset']);
                },
            ])
        );

        $assetEntity = new IndexEntity('', '', IndexType::ASSET);
        $documentEntity = new IndexEntity('', '', IndexType::DOCUMENT);
        $dataObjectEntity = new IndexEntity('', '', IndexType::DATA_OBJECT);

        $this->assertEquals(
            'relation.asset',
            $transformer->transformFieldName('relation', [], $assetEntity)
        );

        $this->assertEquals(
            'relation.document',
            $transformer->transformFieldName('relation', [], $documentEntity)
        );

        $this->assertEquals(
            'relation.object',
            $transformer->transformFieldName('relation', [], $dataObjectEntity)
        );

        $this->assertEquals(
            null,
            $transformer->transformFieldName('foo', [], $dataObjectEntity)
        );
    }

    public function testStopPropagation(): void
    {
        $transformer = new RelationsTransformer(
            $this->createMock(MappingAnalyzerServiceInterface::class)
        );

        $this->assertTrue($transformer->stopPropagation());
    }
}
