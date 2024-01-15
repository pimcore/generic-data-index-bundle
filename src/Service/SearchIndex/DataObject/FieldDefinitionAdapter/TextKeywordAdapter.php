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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;

class TextKeywordAdapter extends TextAdapter
{
    public function getOpenSearchMapping(): array
    {
        $mapping = parent::getOpenSearchMapping();

        $mapping['fields'] = array_merge($mapping['fields'], [
            'raw' => [
                'type' => AttributeType::KEYWORD->value,
            ],
        ]);

        return $mapping;
    }
}
