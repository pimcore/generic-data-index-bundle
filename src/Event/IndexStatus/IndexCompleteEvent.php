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

namespace Pimcore\Bundle\GenericDataIndexBundle\Event\IndexStatus;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires once when the index queue entries of a queued full rebuild have all been processed and the index is
 * complete again. Dispatched by the messenger worker that processes the last batch, so listeners run in the worker.
 */
final class IndexCompleteEvent extends Event
{
    public function __construct(private readonly IndexStatus $status)
    {
    }

    public function getStatus(): IndexStatus
    {
        return $this->status;
    }

    public function getIndexName(): string
    {
        return $this->status->getIndexName();
    }
}
