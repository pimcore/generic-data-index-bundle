<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;

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

}