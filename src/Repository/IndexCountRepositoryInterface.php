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

namespace Pimcore\Bundle\GenericDataIndexBundle\Repository;

use Doctrine\DBAL\Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * The counts the index status is built from.
 *
 * @internal
 */
interface IndexCountRepositoryInterface
{
    /**
     * Elements in the database that belong into the index, with the scope the full rebuilds use: all objects and
     * variants of a class, all data object folders, all assets, all documents.
     *
     * @throws Exception
     */
    public function countElements(IndexStateTarget $target): int;

    /**
     * Index queue entries, dispatched or not; of one index when $elementIndexName is given (class name, "asset",
     * "document" or "data-object-folder").
     *
     * @throws Exception
     */
    public function countQueueEntries(?string $elementIndexName = null): int;
}
