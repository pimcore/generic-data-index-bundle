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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;

/**
 * @internal
 */
interface DefaultSearchInterface extends AdapterSearchInterface
{
    public function addQuery(?QueryInterface $query = null): DefaultSearchInterface;

    public function addSort(FieldSort $sort): DefaultSearchInterface;

    public function addAggregation(Aggregation $aggregation): DefaultSearchInterface;

    public function getQueryList(): QueryList;

    public function getSource(): bool|array|string|null;

    public function setSource(bool|array|string|null $source): AdapterSearchInterface;

    public function getSearchAfter(): ?array;

    public function setSearchAfter(?array $searchAfter): DefaultSearchInterface;
}
