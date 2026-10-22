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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\PathService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\UpdateByQueryServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;
use ReflectionMethod;

/**
 * Regression coverage for PEES-1245: Portal Engine's frontend thumbnail URLs are built via
 * urlencode_ignore_slash() (rawurlencode() per path segment), so a path containing accented or
 * other non-unreserved characters is stored percent-encoded in the search index. When such a
 * folder is moved, rewriteChildrenIndexPaths() must be able to match and rewrite that encoded
 * form as well as the plain form, otherwise the cached thumbnail URL is left stale.
 *
 * @internal
 */
final class PathServiceTest extends Unit
{
    public function testEncodePathSegmentsEncodesAccentedCharactersButKeepsSlashes(): void
    {
        $this->assertSame(
            '/caf%C3%A9/sub-folder',
            $this->encodePathSegments('/café/sub-folder')
        );
    }

    public function testEncodePathSegmentsEncodesSpaces(): void
    {
        $this->assertSame(
            '/my%20folder/asset',
            $this->encodePathSegments('/my folder/asset')
        );
    }

    public function testEncodePathSegmentsLeavesUnreservedCharactersUntouched(): void
    {
        $this->assertSame(
            '/Folder-1/asset_2.jpg',
            $this->encodePathSegments('/Folder-1/asset_2.jpg')
        );
    }

    public function testEmulatedRewriteMovesADescendantToTheNewPath(): void
    {
        $source = $this->rewritePathInSource(
            [
                'system_fields' => [
                    'id' => 7,
                    'path' => '/old/sub/',
                    'fullPath' => '/old/sub/x.jpg',
                    'thumbnail' => '/old/sub/image-thumb__7__preview/x.jpg',
                    'pathLevels' => [['level' => 1, 'name' => 'old'], ['level' => 2, 'name' => 'sub']],
                    'checksum' => 123,
                    'modificationDate' => '2026-01-01T00:00:00+00:00',
                ],
                'custom_fields' => [
                    'PortalEngineBundle' => [
                        'system_fields' => ['thumbnail' => '/cache-buster-42/old/sub/image-thumb__7__pe/x.jpg'],
                    ],
                ],
            ],
            '/old',
            '/new/place'
        );

        $this->assertSame(
            [
                'id' => 7,
                'path' => '/new/place/sub/',
                'fullPath' => '/new/place/sub/x.jpg',
                'thumbnail' => '/new/place/sub/image-thumb__7__preview/x.jpg',
                'pathLevels' => [
                    ['level' => 1, 'name' => 'new'],
                    ['level' => 2, 'name' => 'place'],
                    ['level' => 3, 'name' => 'sub'],
                ],
                'checksum' => 0,
                'modificationDate' => '2026-10-22T00:00:00+00:00',
            ],
            $source['system_fields']
        );
        $this->assertSame(
            '/cache-buster-42/new/place/sub/image-thumb__7__pe/x.jpg',
            $source['custom_fields']['PortalEngineBundle']['system_fields']['thumbnail']
        );
    }

    public function testEmulatedRewriteKeepsAPercentEncodedPortalEngineThumbnailEncoded(): void
    {
        $source = $this->rewritePathInSource(
            [
                'system_fields' => ['path' => '/café/', 'fullPath' => '/café/x.jpg'],
                'custom_fields' => [
                    'PortalEngineBundle' => ['system_fields' => ['thumbnail' => '/caf%C3%A9/image-thumb__7__pe/x.jpg']],
                ],
            ],
            '/café',
            '/thé'
        );

        $this->assertSame('/thé/x.jpg', $source['system_fields']['fullPath']);
        $this->assertSame(
            '/th%C3%A9/image-thumb__7__pe/x.jpg',
            $source['custom_fields']['PortalEngineBundle']['system_fields']['thumbnail']
        );
    }

    public function testEmulatedRewriteOnlyTouchesTheDatesOfTheMovedElementItself(): void
    {
        $systemFields = ['id' => 3, 'path' => '/', 'fullPath' => '/old', 'pathLevels' => [], 'checksum' => 5];

        $source = $this->rewritePathInSource(['system_fields' => $systemFields], '/old', '/new');

        $expected = $systemFields;
        $expected['checksum'] = 0;
        $expected['modificationDate'] = '2026-10-22T00:00:00+00:00';

        $this->assertSame($expected, $source['system_fields']);
    }

    /**
     * Calls the PHP version of the painless path rewrite with the script params updatePath() builds.
     */
    private function rewritePathInSource(array $source, string $currentPath, string $newPath): array
    {
        $service = $this->createPathService();
        $method = new ReflectionMethod(PathService::class, 'rewritePathInSource');

        return $method->invoke($service, $source, [
            'currentPath' => $currentPath . '/',
            'newPath' => $newPath . '/',
            'currentPathEncoded' => $this->encodePathSegments($currentPath) . '/',
            'newPathEncoded' => $this->encodePathSegments($newPath) . '/',
            'now' => '2026-10-22T00:00:00+00:00',
        ]);
    }

    private function createPathService(): PathService
    {
        return new PathService(
            $this->makeEmpty(SearchClientInterface::class),
            $this->makeEmpty(AdapterServiceInterface::class),
            $this->makeEmpty(UpdateByQueryServiceInterface::class),
        );
    }

    private function encodePathSegments(string $path): string
    {
        $service = $this->createPathService();

        $method = new ReflectionMethod(PathService::class, 'encodePathSegments');
        $method->setAccessible(true);

        return $method->invoke($service, $path);
    }
}
