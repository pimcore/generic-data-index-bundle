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
