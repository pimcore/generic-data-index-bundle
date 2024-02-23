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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;

/**
 * @internal
 */
interface OpenSearchSearchInterface extends AdapterSearchInterface
{
    public function addQuery(QueryInterface $query = null): OpenSearchSearchInterface;

    public function addSort(FieldSort $sort): OpenSearchSearchInterface;

    public function addAggregation(Aggregation $aggregation): OpenSearchSearchInterface;

    public function getQueryList(): QueryList;

    public function getSource(): bool|array|string|null;

    public function setSource(bool|array|string|null $source): AdapterSearchInterface;
}
