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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

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
