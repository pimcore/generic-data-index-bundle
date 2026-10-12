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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\DefaultSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;

/**
 * @internal
 */
final class SearchKnnToArrayTest extends Unit
{
    public function testSearchEmitsTopLevelKnnWhenSet(): void
    {
        $search = (new Search())->setKnn([
            'field' => 'custom_fields.emb_nomic_vision',
            'query_vector' => [0.1, 0.2, 0.3],
            'k' => 5,
            'num_candidates' => 50,
        ]);

        $result = $search->toArray();

        self::assertArrayHasKey('knn', $result);
        self::assertSame([
            'field' => 'custom_fields.emb_nomic_vision',
            'query_vector' => [0.1, 0.2, 0.3],
            'k' => 5,
            'num_candidates' => 50,
        ], $result['knn']);
    }

    public function testSearchOmitsKnnWhenUnset(): void
    {
        self::assertArrayNotHasKey('knn', (new Search())->toArray());
    }

    public function testGetKnnReturnsNullByDefault(): void
    {
        self::assertNull((new Search())->getKnn());
    }

    public function testGetKnnReturnsSetValue(): void
    {
        $knn = ['field' => 'f', 'query_vector' => [0.1], 'k' => 1, 'num_candidates' => 10];
        $search = (new Search())->setKnn($knn);

        self::assertSame($knn, $search->getKnn());
    }
}
