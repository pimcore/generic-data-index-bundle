<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;

interface IndexStatsServiceInterface
{
    public function getStats(): IndexStats;
}