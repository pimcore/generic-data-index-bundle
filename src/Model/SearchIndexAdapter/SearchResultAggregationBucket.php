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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

readonly class SearchResultAggregationBucket
{
    public function __construct(
        private string|int $key,
        private int $docCount,
    ) {
    }

    public function getKey(): string|int
    {
        return $this->key;
    }

    public function getDocCount(): int
    {
        return $this->docCount;
    }
}
