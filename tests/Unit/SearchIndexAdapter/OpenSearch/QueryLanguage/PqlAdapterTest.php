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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\OpenSearch\QueryLanguage;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\PqlAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\SubQueriesProcessorInterface;

/**
 * @internal
 */
final class PqlAdapterTest extends Unit
{
    public function testTransformFieldName(): void
    {
        $fieldNameTransformer1 = $this->makeEmpty(FieldNameTransformerInterface::class, [
            'stopPropagation' => false,
            'transformFieldName' => function () {
                return 'transformer-1';
            },
        ]);

        $fieldNameTransformer2 = $this->makeEmpty(FieldNameTransformerInterface::class, [
            'stopPropagation' => true,
            'transformFieldName' => function () {
                return 'transformer-2';
            },
        ]);

        $pqlAdapter = $this->createPqlAdapter([$fieldNameTransformer1, $fieldNameTransformer2]);
        $this->assertEquals(
            'transformer-2',
            $pqlAdapter->transformFieldName('test', [], null)
        );

        $pqlAdapter = $this->createPqlAdapter([$fieldNameTransformer2, $fieldNameTransformer1]);
        $this->assertEquals(
            'transformer-2',
            $pqlAdapter->transformFieldName('test', [], null)
        );

        $pqlAdapter = $this->createPqlAdapter([$fieldNameTransformer1]);
        $this->assertEquals(
            'transformer-1',
            $pqlAdapter->transformFieldName('test', [], null)
        );
    }

    private function createPqlAdapter(array $fieldNameTransformers): PqlAdapter
    {
        return new PqlAdapter(
            $this->makeEmpty(SubQueriesProcessorInterface::class),
            $fieldNameTransformers,
            [],
            [],
        );
    }
}
