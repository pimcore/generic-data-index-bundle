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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\WildcardFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;

/**
 * @internal
 */
final class TextKeywordAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        $searchAnalyzerAttributes = $this->searchIndexConfigService->getSearchAnalyzerAttributes();

        return [
            'type' => AttributeType::TEXT->value,
            'fields' => array_merge(
                $searchAnalyzerAttributes[AttributeType::TEXT->value]['fields'] ?? [],
                [
                    'keyword' => [
                        'type' => AttributeType::KEYWORD->value,
                    ],
                ]
            ),
        ];
    }

    public function applySearchFilter(AssetMetaDataFilter $filter, AdapterSearchInterface $adapterSearch): void
    {
        if ($filter->getType() !== $this->getType()) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s does not support filter type "%s" for filter "%s"',
                    self::class,
                    $filter->getType(),
                    $filter->getName()
                )
            );
        }

        $searchTerm = $filter->getData();
        if (!is_string($searchTerm)) {
            throw new InvalidArgumentException('Search term must be a string');
        }

        if (empty($searchTerm)) {
            return;
        }

        $adapterSearch
            ->addQuery(
                new WildcardFilter(
                    $this->getSearchFilterFieldPath($filter),
                    $searchTerm
                )
            );
    }

    protected function getSearchFilterFieldPath(AssetMetaDataFilter|AssetMetaDataAggregation $filter): string
    {
        return parent::getSearchFilterFieldPath($filter) . '.keyword';
    }
}
