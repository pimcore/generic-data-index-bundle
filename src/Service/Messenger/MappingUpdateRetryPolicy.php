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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexTimeoutException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Throwable;

/**
 * @internal
 */
final class MappingUpdateRetryPolicy implements MappingUpdateRetryPolicyInterface
{
    public function rethrow(Exception $failure): never
    {
        $timeout = $this->findTimeout($failure);
        if ($timeout === null) {
            throw $failure;
        }

        throw new UnrecoverableMessageHandlingException(
            sprintf(
                '%s. A retry with the same budget would run out of it as well: raise reindex_settings.max_polls'
                . ' (or poll_interval), then retry the message from the failed transport.',
                rtrim($timeout->getMessage(), '.')
            ),
            0,
            $failure
        );
    }

    /**
     * The timeout is wrapped on its way up (for example in a ClassDefinitionIndexUpdateFailedException), so the
     * whole cause chain is searched.
     */
    private function findTimeout(Throwable $failure): ?ReindexTimeoutException
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ReindexTimeoutException) {
                return $cause;
            }
        }

        return null;
    }
}
