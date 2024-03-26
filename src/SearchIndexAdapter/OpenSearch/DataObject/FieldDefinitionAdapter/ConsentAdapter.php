<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;

/**
 * @internal
 */
final class ConsentAdapter extends AbstractAdapter
{

    public function getIndexMapping(): array
    {
        return [
            'properties' => [
                'consent' => [
                    'type' => AttributeType::BOOLEAN->value
                ],
                'noteId' => [
                    'type' => AttributeType::LONG->value
                ]
            ]
        ];
    }
}