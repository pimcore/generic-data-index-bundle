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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\User;

/**
 * @internal
 */
final class AssetLazyLoadingHandler implements AssetLazyLoadingHandlerInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AssetSearchServiceInterface $assetSearchService,
        private readonly ?User $user = null
    ) {
    }

    public function lazyLoad(AssetSearchResultItem $item): void
    {
        $indexItem = $this->assetSearchService->byId($item->getId(), $this->user ?? null);
        if (!$indexItem) {
            $this->logger->warning('Asset not found in search index', ['id' => $item->getId()]);

            return;
        }

        $item
            ->setHasChildren($indexItem->isHasChildren())
            ->setFileSize($indexItem->getFileSize())
            ->setSearchIndexData($indexItem->getSearchIndexData())
            ->setHasWorkflowWithPermissions($indexItem->isHasWorkflowWithPermissions());
    }

    public function apply(AssetSearchResultItem $item, ?User $user): AssetSearchResultItem
    {
        $handler = new AssetLazyLoadingHandler($this->assetSearchService, $user);

        return $item->withLazyLoadingHandler($handler);
    }
}
