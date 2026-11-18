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

use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface IndexQueueServiceInterface
{
    /**
     * @param bool $enqueueDependentItems false skips the elements that depend on the given one (for example the
     *                                    elements referencing an asset) on the queued path; it does not reach the
     *                                    deferred path taken with $enqueueRelatedItemsAsync
     */
    public function updateIndexQueue(
        ElementInterface $element,
        string $operation,
        bool $processSynchronously = false,
        bool $enqueueRelatedItems = true,
        bool $enqueueRelatedItemsAsync = false,
        bool $enqueueDependentItems = true
    ): IndexQueueService;

    /**
     * @param IndexQueue[] $entries
     */
    public function handleIndexQueueEntries(array $entries): void;

    public function commit(?string $refreshIndex = null): IndexQueueService;
}
