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

/**
 * @internal
 */
final class UrlSlugAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            'type' => AttributeType::NESTED->value,
            'properties' => [
                'siteId' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'slug' => [
                    'type' => AttributeType::TEXT->value,
                ],
            ],
        ];
    }
}
