<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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

    public function getSearchAfter(): ?array;

    public function setSearchAfter(?array $searchAfter): OpenSearchSearchInterface;
}
