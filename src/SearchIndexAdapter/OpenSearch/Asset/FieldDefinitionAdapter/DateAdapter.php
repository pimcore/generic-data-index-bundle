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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Carbon\Carbon;
use DateTimeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidValueException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
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

    /**
     * @param OpenSearchSearchInterface $adapterSearch
     *
     * @throws InvalidValueException
     */
    public function applySearchFilter(AssetMetaDataFilter $filter, AdapterSearchInterface $adapterSearch): void
    {
        $dateFilter = DateFilter::createFromArray($this->getSearchFilterFieldPath($filter), $filter->getData());
        $adapterSearch->addQuery($dateFilter);
    }


}
