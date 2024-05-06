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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface IndexQueueServiceInterface
{
    public function updateIndexQueue(
        ElementInterface $element,
        string $operation,
        bool $processSynchronously = false
    ): IndexQueueService;

    /**
     * @param IndexQueue[] $entries
     */
    public function handleIndexQueueEntries(array $entries): void;

    public function commit(?string $refreshIndex = null): IndexQueueService;
}
