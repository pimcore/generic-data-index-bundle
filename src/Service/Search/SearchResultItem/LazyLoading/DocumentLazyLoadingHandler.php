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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\User;

/**
 * @internal
 */
final class DocumentLazyLoadingHandler implements DocumentLazyLoadingHandlerInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly DocumentSearchServiceInterface $documentSearchService,
        private readonly ?User $user = null
    ) {
    }

    public function lazyLoad(DocumentSearchResultItem $item): void
    {
        $indexItem = $this->documentSearchService->byId($item->getId(), $this->user ?? null);
        if (!$indexItem) {
            $this->logger->warning('Document not found in search index', ['id' => $item->getId()]);

            return;
        }

        $item
            ->setHasChildren($indexItem->isHasChildren())
            ->setSearchIndexData($indexItem->getSearchIndexData())
            ->setHasWorkflowWithPermissions($indexItem->isHasWorkflowWithPermissions());
    }

    public function apply(DocumentSearchResultItem $item, ?User $user): DocumentSearchResultItem
    {
        $handler = new DocumentLazyLoadingHandler($this->documentSearchService, $user);

        return $item->withLazyLoadingHandler($handler);
    }
}
