<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Model\User;

/**
 * @internal
 */
interface DocumentLazyLoadingHandlerInterface
{
    public function lazyLoad(DocumentSearchResultItem $item): void;

    public function apply(DocumentSearchResultItem $item, ?User $user): DocumentSearchResultItem;

}