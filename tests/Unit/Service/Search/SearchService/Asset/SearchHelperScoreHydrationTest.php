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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchService\Asset;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\SearchHelper;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandlerService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Regression coverage at the hydration seam: the per-hit relevance score must be carried from the
 * adapter {@see SearchResultHit} onto the hydrated result item - the accessor-only tests in
 * SearchResultItemScoreTest would still pass if this wiring were removed.
 *
 * @internal
 */
final class SearchHelperScoreHydrationTest extends Unit
{
    public function testHydrationCarriesTheHitScoreOntoTheResultItem(): void
    {
        $item = $this->searchHelper()->hydrateSearchResultHit($this->hit(0.8734), []);

        $this->assertSame(0.8734, $item->getScore());
    }

    public function testHydrationKeepsScoreNullWhenTheQueryProducedNone(): void
    {
        $item = $this->searchHelper()->hydrateSearchResultHit($this->hit(null), []);

        $this->assertNull($item->getScore());
    }

    private function searchHelper(): SearchHelper
    {
        $permissionService = $this->createMock(PermissionServiceInterface::class);
        $permissionService->method('getAssetPermissions')->willReturn(new AssetPermissions());

        return new SearchHelper(
            new AssetSearchResultDenormalizer(new AssetTypeSerializationHandlerService(new ServiceLocator([]))),
            $permissionService,
            $this->createMock(RuntimeCacheResolverInterface::class),
            $this->createMock(SearchIndexServiceInterface::class),
            $this->createMock(SearchModifierServiceInterface::class),
            $this->createMock(UserPermissionServiceInterface::class),
        );
    }

    private function hit(?float $score): SearchResultHit
    {
        return new SearchResultHit(
            id: '42',
            index: 'pimcore_asset',
            score: $score,
            source: [
                'system_fields' => [
                    'id' => 42,
                    'parentId' => 1,
                    'type' => 'image',
                    'key' => 'test.jpg',
                    'path' => '/',
                    'fullPath' => '/test.jpg',
                    'mimetype' => 'image/jpeg',
                    'userOwner' => 0,
                    'userModification' => 0,
                    'locked' => null,
                    'creationDate' => '2026-01-01T00:00:00+00:00',
                    'modificationDate' => '2026-01-01T00:00:00+00:00',
                    'fileSize' => 1024,
                    'hasWorkflowWithPermissions' => false,
                    'hasChildren' => false,
                ],
                'standard_fields' => [],
            ],
            sort: null,
        );
    }
}
