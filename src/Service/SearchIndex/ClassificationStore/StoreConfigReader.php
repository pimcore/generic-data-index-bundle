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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore;

use Generator;

/**
 * @internal
 */
final class StoreConfigReader implements StoreConfigReaderInterface
{
    public const DEFAULT_PAGE_SIZE = 200;

    public function __construct(
        private readonly StoreConfigListingFactoryInterface $listingFactory,
        private readonly int $groupPageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    public function getGroups(int $storeId): Generator
    {
        $offset = 0;

        while (true) {
            $listing = $this->listingFactory->createGroupListing();
            $listing->setCondition('storeId = :storeId', ['storeId' => $storeId]);
            // a page of an unordered listing can skip a row and read another one twice
            $listing->setOrderKey('id');
            $listing->setOrder('ASC');
            $listing->setLimit($this->groupPageSize);
            $listing->setOffset($offset);

            $page = $listing->getList();
            yield from $page;

            if (count($page) < $this->groupPageSize) {
                return;
            }

            $offset += $this->groupPageSize;
        }
    }

    public function getAllGroups(int $storeId): array
    {
        $listing = $this->listingFactory->createGroupListing();
        $listing->setCondition('storeId = :storeId', ['storeId' => $storeId]);

        return $listing->getList();
    }

    public function getKeyRelations(int $groupId): array
    {
        $listing = $this->listingFactory->createKeyGroupRelationListing();
        $listing->addConditionParam('groupId = ?', $groupId);

        return $listing->getList();
    }
}
