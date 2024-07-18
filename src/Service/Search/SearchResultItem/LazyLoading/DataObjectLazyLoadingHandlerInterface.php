<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Model\User;

/**
 * @internal
 */
interface DataObjectLazyLoadingHandlerInterface
{
    public function lazyLoad(DataObjectSearchResultItem $item): void;

    public function apply(DataObjectSearchResultItem $item, ?User $user): DataObjectSearchResultItem;

}