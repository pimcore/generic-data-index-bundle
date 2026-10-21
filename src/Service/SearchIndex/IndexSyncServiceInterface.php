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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexSyncAlreadyRunningException;

interface IndexSyncServiceInterface
{
    /**
     * Brings the search indices back in line with the database: the mappings of all data object classes, assets
     * and documents are updated and every element is added to the index queue, which the messenger workers then
     * process. Equivalent to `generic-data-index:update:index` without the recreate option, so existing indices
     * are kept and updated in place. Only an index whose changed mapping cannot be applied to the documents it
     * already holds (e.g. after a field type change) is recreated and refilled from the queue, exactly as the
     * command does.
     *
     * Returns once everything is queued, not once the queue is drained.
     *
     * @throws IndexSyncAlreadyRunningException when another synchronization is running, or took the lock over
     * @throws Exception
     */
    public function syncAll(): void;
}
