<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

use Carbon\Carbon;

class TimeService
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