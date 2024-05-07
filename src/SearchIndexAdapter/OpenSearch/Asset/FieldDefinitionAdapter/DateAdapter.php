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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Carbon\Carbon;
use DateTimeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;

/**
 * @internal
 */
final class DateAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'type' => AttributeType::DATE->value,
        ];
    }

    public function normalize(mixed $value): ?string
    {
        if (!empty($value)) {
            return Carbon::createFromTimestamp($value)->format(DateTimeInterface::ATOM);
        }

        return null;
    }

    public function applySearchFilter(AssetMetaDataFilter $filter, AdapterSearchInterface $adapterSearch): void
    {
        if (!is_array($filter->getData())) {
            $this->throwInvalidFilterValueArgumentException($filter->getData(), $filter);
        }

        $dateFilter = DateFilter::createFromArray($this->getSearchFilterFieldPath($filter), $filter->getData());
        $adapterSearch->addQuery($dateFilter);
    }
}
