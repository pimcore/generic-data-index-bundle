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
final class LinkAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            'properties' => [
                'text' => [
                    'type' => AttributeType::TEXT->value,
                ],
                'internalType' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'internal' => [
                    'type' => AttributeType::LONG->value,
                ],
                'direct' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'linktype' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'target' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'parameters' => [
                    'type' => AttributeType::TEXT->value,
                ],
                'anchor' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'title' => [
                    'type' => AttributeType::TEXT->value,
                ],
                'accesskey' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'rel' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'tabindex' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'class' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                'attributes' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                '_fieldname' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
                '_language' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
            ],
        ];
    }
}
