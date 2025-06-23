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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter;

use Carbon\Carbon;
use DateTimeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\DateFilter;
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
            return Carbon::createFromTimestamp($value, date_default_timezone_get())->format(DateTimeInterface::ATOM);
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
