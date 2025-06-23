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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;

interface AdapterInterface
{
    public function setType(string $type): self;

    public function getType(): string;

    public function getIndexMapping(): array;

    /**
     * Used to normalize the data for the search index
     */
    public function normalize(mixed $value): mixed;

    /**
     * @throws InvalidArgumentException
     */
    public function applySearchFilter(AssetMetaDataFilter $filter, AdapterSearchInterface $adapterSearch): void;

    public function getSearchFilterAggregation(AssetMetaDataAggregation $aggregation): ?Aggregation;
}
