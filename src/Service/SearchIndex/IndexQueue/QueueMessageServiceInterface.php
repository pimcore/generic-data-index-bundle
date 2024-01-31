<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Doctrine\DBAL\Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\EnqueueAssetsException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Tag;

/**
 * @internal
 */
interface QueueMessageServiceInterface
{
    public function handleMessage(
        int $entriesCount,
        int $maxBatchSize,
        array $entries
    ): void;

    public function getMaxBatchSize(
        int $entriesCount,
        int $workerCount,
        int $minBatchSize,
        int $maxBatchSize
    ): int;
}
