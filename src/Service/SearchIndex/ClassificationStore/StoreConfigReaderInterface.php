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
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;

/**
 * Reads the configuration of a classification store for the mapping and the document, bounding what is
 * hydrated at once.
 *
 * @internal
 */
interface StoreConfigReaderInterface
{
    /**
     * The groups of a store, in pages: a field without a group restriction maps every group of its store, so
     * this listing is unbounded by configuration (5,640 groups on the measured ETIM store).
     *
     * @return Generator<int, GroupConfig>
     */
    public function getGroups(int $storeId): Generator;

    /**
     * The groups of a store in one query. For the paths that run per indexed element: they read the groups of
     * the store for every single element, where one query per page costs more than the hydration it bounds.
     *
     * @return GroupConfig[]
     */
    public function getAllGroups(int $storeId): array;

    /**
     * The key assignments of one group, in one query: they are bounded by the group, and paging them measured
     * worse than reading them at once.
     *
     * @return KeyGroupRelation[]
     */
    public function getKeyRelations(int $groupId): array;
}
