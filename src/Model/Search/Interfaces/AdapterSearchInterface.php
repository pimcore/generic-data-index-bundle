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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort;

interface AdapterSearchInterface
{
    public function getFrom(): ?int;

    public function setFrom(?int $from): AdapterSearchInterface;

    public function getSize(): ?int;

    public function setSize(?int $size): AdapterSearchInterface;

    public function getSource(): bool|array|string|null;

    public function setSource(bool|array|string|null $source): AdapterSearchInterface;

    public function addQuery(QueryInterface $query = null): AdapterSearchInterface;

    public function addSort(FieldSort $sort): AdapterSearchInterface;

    public function addAggregation(Aggregation $aggregation): AdapterSearchInterface;

    public function getQueryList(): QueryList;

    public function toArray(): array;
}
