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
        $this->assertSame(
            [
                'c' => ['standard_fields' => ['c' => [
                    'price' => ['e' => true],
                    'l_text' => ['c' => ['en' => ['e' => true]]],
                    'items' => ['c' => ['GdiE2eItem' => ['c' => ['i_text' => ['e' => true]]]]],
                ]]],
            ],
            json_decode(json_encode($script['params']['tree'], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
            'The paths travel as a trie of children (c) and strip markers (e)'
        );
        foreach (['price', 'l_text', 'items', 'GdiE2eItem', 'i_text', 'standard_fields'] as $segment) {
            $this->assertStringNotContainsString($segment, $script['source']);
        }
    }

    /**
     * A segment such as "0" (a classification store key or group id) would turn a PHP array into a JSON list,
     * which the script could not look up by name.
     */
    public function testTheTreeIsEncodedAsJsonObjectsForNumericSegments(): void
    {
        $tree = (new ReindexStripScript([['standard_fields', 'cs', '0'], ['standard_fields', 'cs', '1']]))
            ->toRequestScript()['params']['tree'];

        $this->assertSame(
            '{"c":{"standard_fields":{"c":{"cs":{"c":{"0":{"e":true},"1":{"e":true}}}}}}}',
            json_encode($tree, JSON_THROW_ON_ERROR)
        );
    }

    public function testAPathInsideAStrippedPathKeepsTheStripMarkerOfItsParent(): void
    {
        $tree = (new ReindexStripScript([['standard_fields', 'spec'], ['standard_fields', 'spec', 'width']]))
            ->toRequestScript()['params']['tree'];

        $this->assertTrue($tree->c->standard_fields->c->spec->e);
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
        $this->assertStringContainsString('params.tree', $source);
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
