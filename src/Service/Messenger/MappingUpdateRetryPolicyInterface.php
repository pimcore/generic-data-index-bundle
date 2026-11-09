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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Messenger;

use Exception;

/**
 * Decides how the failure of a mapping update message is reported to Messenger: as a failure to retry, or as one
 * that goes straight to the failed transport.
 *
 * @internal
 */
interface MappingUpdateRetryPolicyInterface
{
    /**
     * Rethrows the failure of a mapping update message. A copy that ran out of its budget is rethrown as an
     * unrecoverable failure, because a retry with the same budget runs out of it as well; every other failure is
     * rethrown as it is, so Messenger retries it.
     *
     * @throws Exception
     */
    public function rethrow(Exception $failure): never;
}
