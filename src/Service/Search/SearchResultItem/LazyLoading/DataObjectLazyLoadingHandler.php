<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\User;

/**
 * @internal
 */
final class DataObjectLazyLoadingHandler implements DataObjectLazyLoadingHandlerInterface
{
    use LoggerAwareTrait;
    public function __construct(
        private readonly DataObjectSearchServiceInterface $dataObjectSearchService,
        private readonly ?User $user = null
    )
    {
    }

    public function lazyLoad(DataObjectSearchResultItem $item): void
    {
        $indexItem = $this->dataObjectSearchService->byId($item->getId(), $this->user ?? null);
        if (!$indexItem) {
            $this->logger->warning('Data object not found in search index', ['id' => $item->getId()]);
            return;
        }

        $item
            ->setHasChildren($indexItem->isHasChildren())
            ->setSearchIndexData($indexItem->getSearchIndexData())
            ->setHasWorkflowWithPermissions($indexItem->isHasWorkflowWithPermissions());
    }

    public function apply(DataObjectSearchResultItem $item, ?User $user): DataObjectSearchResultItem
    {
        $handler = new DataObjectLazyLoadingHandler($this->dataObjectSearchService, $user);
        return $item->withLazyLoadingHandler($handler);
    }
}