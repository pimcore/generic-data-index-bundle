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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
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
    ) {
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
