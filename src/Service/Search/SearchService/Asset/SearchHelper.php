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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\AbstractSearchHelper;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final class SearchHelper extends AbstractSearchHelper
{
    public const ASSET_SEARCH = 'asset_search';

    public function __construct(
        private readonly AssetSearchResultDenormalizer $denormalizer,
        private readonly PermissionServiceInterface $permissionService,
        private readonly RuntimeCacheResolverInterface $runtimeCacheResolver,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchModifierServiceInterface $searchModifierService,
        private readonly UserPermissionServiceInterface $userPermissionService,
    ) {
        parent::__construct(
            $this->searchIndexService,
            $this->searchModifierService,
            $this->userPermissionService
        );
    }

    public function hydrateSearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        ?User $user = null
    ): ElementSearchResultItemInterface {
        $source = $searchResultHit->getSource();

        $source[FieldCategory::SYSTEM_FIELDS->value][SystemField::HAS_CHILDREN->value] =
            ($childrenCounts[$searchResultHit->getId()] ?? 0) > 0;

        $result = $this->denormalizer->denormalize(
            $source,
            AssetSearchResult::class
        );

        $this->runtimeCacheResolver->save($result, self::ASSET_SEARCH . '_' . $result->getId());
        $result->setPermissions(
            $this->permissionService->getAssetPermissions(
                $result,
                $user
            )
        );

        return $result;
    }
}
