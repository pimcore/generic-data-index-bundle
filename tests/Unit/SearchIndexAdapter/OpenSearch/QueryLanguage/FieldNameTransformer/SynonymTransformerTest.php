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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformer\SynonymTransformer;

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
