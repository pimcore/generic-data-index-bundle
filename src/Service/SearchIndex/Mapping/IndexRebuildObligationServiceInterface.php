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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;

/**
 * Remembers per index that a mapping update may have left documents only a rebuild from the database repairs
 * (stripped fields, a recreated index), until that rebuild is queued. The copy changes the live index first and the
 * rebuild is queued afterwards; when a run fails in between, the next run sees a live mapping that already matches
 * and a copy that changes nothing, and would skip the rebuild. The obligation makes it queue the rebuild anyway.
 *
 * @internal
 */
interface IndexRebuildObligationServiceInterface
{
    /**
     * An earlier mapping update of the index did not get as far as queuing its rebuild.
     */
    public function isOwed(IndexStateTarget $target): bool;

    /**
     * Called right before a mapping update that may change the live index.
     *
     * @throws Exception
     */
    public function owe(IndexStateTarget $target): void;

    /**
     * Called once the rebuild is queued, or when the mapping update needed none.
     */
    public function settle(IndexStateTarget $target): void;
}
