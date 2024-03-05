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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
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
