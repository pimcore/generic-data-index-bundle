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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\QueryLanguage\FieldNameTransformer\SynonymTransformer;

/**
 * @internal
 */
final class SynonymTransformerTest extends Unit
{
    public function testTransformFieldName(): void
    {
        $transformer = new SynonymTransformer();

        $this->assertEquals(
            null,
            $transformer->transformFieldName('filename', [], null)
        );

        $this->assertEquals(
            'key',
            $transformer->transformFieldName('filename', [], new IndexEntity(IndexName::ASSET->value, IndexName::ASSET->value, IndexType::ASSET))
        );

        $this->assertEquals(
            'fullPath',
            $transformer->transformFieldName('fullpath', [], null)
        );

    }

    public function testStopPropagation(): void
    {
        $transformer = new SynonymTransformer();

        $this->assertFalse($transformer->stopPropagation());
    }
}
