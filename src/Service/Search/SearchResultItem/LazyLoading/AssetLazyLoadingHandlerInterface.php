<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Model\User;

/**
 * @internal
 */
interface AssetLazyLoadingHandlerInterface
{
    public function lazyLoad(AssetSearchResultItem $item): void;

    public function apply(AssetSearchResultItem $item, ?User $user): AssetSearchResultItem;

}