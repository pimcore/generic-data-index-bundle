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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexState;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * @internal
 */
interface IndexStateServiceInterface
{
    /**
     * Stored instead of a mapping checksum while an index is being rebuilt by other means
     * (snapshot import); never equals a real checksum, so an unfinished rebuild is redone.
     */
    public const INVALIDATED = 'invalidated';

    /**
     * @throws Exception
     */
    public function getCurrentState(IndexStateTarget $target): IndexState;

    /**
     * The mapping checksum of a class index is the one the class definition reindex has always
     * stored.
     */
    public function getStoredState(IndexStateTarget $target): IndexState;

    public function indexExists(IndexStateTarget $target): bool;

    /**
     * Stores all values of $state that are not null. The mapping checksum of a class index is
     * left to the class definition reindex unless $includeClassMapping is set.
     *
     * @throws Exception
     */
    public function store(IndexStateTarget $target, IndexState $state, bool $includeClassMapping = false): void;

    /**
     * Updates values that are already stored, after another code path brought the index up to
     * date. Indices that never ran the extended deployment reindex get no entries.
     *
     * @throws Exception
     */
    public function refresh(
        IndexStateTarget $target,
        bool $mapping,
        bool $settings,
        bool $dataVersion
    ): void;

    /**
     * @throws Exception
     */
    public function invalidateMapping(IndexStateTarget $target): void;
}
