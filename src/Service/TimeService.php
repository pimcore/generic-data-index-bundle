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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Carbon\Carbon;

/**
 * @internal
 */
final class TimeService implements TimeServiceInterface
{
    /**
     * Get current timestamp + milliseconds
     */
    public function getCurrentMillisecondTimestamp(): int
    {
        $carbonNow = Carbon::now();

        return (int)($carbonNow->getTimestamp() . str_pad((string)$carbonNow->milli, 3, '0'));
    }
}
