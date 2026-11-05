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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Status;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Status\ClientConfiguration;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Status\QueueStatus;

/**
 * Read-only status of the Generic Data Index for monitoring and diagnostics.
 *
 * Public API: covered by the backward compatibility promise. Nothing here changes data, connects to the
 * search engine or exposes hosts, credentials or file paths.
 */
interface IndexStatusServiceInterface
{
    /**
     * Counts of the index queue table, read with one aggregate query.
     *
     * @throws IndexStatusException when the queue table cannot be read
     */
    public function getQueueStatus(): QueueStatus;

    /**
     * The configured search client (type and name) and the index prefix. Reads the bundle configuration only.
     */
    public function getClientConfiguration(): ClientConfiguration;
}
