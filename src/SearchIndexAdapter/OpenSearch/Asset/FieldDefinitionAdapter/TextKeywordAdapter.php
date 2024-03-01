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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidValueException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
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
            throw new InvalidValueException(
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
            throw new InvalidValueException('Search term must be a string');
        }

        if (!str_contains($searchTerm, '*')) {
            $searchTerm = '*' . $searchTerm . '*';
        }

        $adapterSearch
            ->addQuery(
                new BoolQuery([
                    ConditionType::FILTER->value => [
                        'wildcard' => [
                            $this->getSearchFilterFieldPath($filter) => [
                                'value' => $searchTerm,
                                'case_insensitive' => false,
                            ],
                        ],
                    ],
                ])
            );
    }

    protected function getSearchFilterFieldPath(AssetMetaDataFilter $filter): string
    {
        return parent::getSearchFilterFieldPath($filter) . '.keyword';
    }
}
