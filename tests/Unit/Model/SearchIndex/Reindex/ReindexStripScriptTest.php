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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\SearchIndex\Reindex;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex\ReindexStripScript;

/**
 * @internal
 */
final class ReindexStripScriptTest extends Unit
{
    public function testPathsArePassedAsParamsAndNeverEnterTheSource(): void
    {
        $paths = [
            ['standard_fields', 'price'],
            ['standard_fields', 'l_text', 'en'],
            ['standard_fields', 'items', 'GdiE2eItem', 'i_text'],
        ];

        $script = (new ReindexStripScript($paths))->toRequestScript();

        $this->assertSame('painless', $script['lang']);
        $this->assertSame($paths, $script['params']['paths']);
        foreach (['price', 'l_text', 'items', 'GdiE2eItem', 'i_text', 'standard_fields'] as $segment) {
            $this->assertStringNotContainsString($segment, $script['source']);
        }
    }

    public function testTheSourceIsTheSameForEveryPathList(): void
    {
        $a = (new ReindexStripScript([['a']]))->toRequestScript()['source'];
        $b = (new ReindexStripScript([['x', 'y'], ['z']]))->toRequestScript()['source'];

        $this->assertSame($a, $b, 'A fixed source lets the engine cache the compiled script');
    }

    public function testTheSourceWalksMapsAndLists(): void
    {
        $source = (new ReindexStripScript([['a']]))->toRequestScript()['source'];

        $this->assertStringContainsString('instanceof List', $source);
        $this->assertStringContainsString('instanceof Map', $source);
        $this->assertStringContainsString('params.paths', $source);
        $this->assertStringContainsString('ctx._source', $source);
    }

    /**
     * @dataProvider invalidPathsProvider
     */
    public function testInvalidPathsAreRejected(array $paths): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReindexStripScript($paths);
    }

    public static function invalidPathsProvider(): array
    {
        return [
            'no paths' => [[]],
            'empty path' => [[[]]],
            'empty segment' => [[['standard_fields', '']]],
            'non-string segment' => [[['standard_fields', 1]]],
            'path not a list' => [[['a' => 'standard_fields']]],
        ];
    }
}
