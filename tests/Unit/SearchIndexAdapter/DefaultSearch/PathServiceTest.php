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

    /**
     * PEES-2039: thumbnails can be indexed with any configured frontend prefix, so the rewrite
     * script tries the longest prefix first and the plain path (empty prefix) last.
     */
    public function testThumbnailPathPrefixesAreUniqueLongestFirstAndEndWithEmptyPrefix(): void
    {
        $service = new PathService(
            $this->makeEmpty(SearchClientInterface::class),
            $this->makeEmpty(AdapterServiceInterface::class),
            [
                'source' => '/dam',
                'thumbnail' => 'https://cdn.example.com/dam',
                'thumbnail_deferred' => '/dam',
                'unrelated' => null,
            ],
        );

        $method = new ReflectionMethod(PathService::class, 'getThumbnailPathPrefixes');

        $this->assertSame(['https://cdn.example.com/dam', '/dam', ''], $method->invoke($service));
    }

    public function testThumbnailPathPrefixesWithoutConfiguredPrefixes(): void
    {
        $service = new PathService(
            $this->makeEmpty(SearchClientInterface::class),
            $this->makeEmpty(AdapterServiceInterface::class),
        );

        $method = new ReflectionMethod(PathService::class, 'getThumbnailPathPrefixes');

        $this->assertSame([''], $method->invoke($service));
    }

    private function encodePathSegments(string $path): string
    {
        $service = new PathService(
            $this->makeEmpty(SearchClientInterface::class),
            $this->makeEmpty(AdapterServiceInterface::class),
        );

        $method = new ReflectionMethod(PathService::class, 'encodePathSegments');
        $method->setAccessible(true);

        return $method->invoke($service, $path);
    }
}
