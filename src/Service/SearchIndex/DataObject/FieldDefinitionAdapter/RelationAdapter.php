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

use Carbon\Carbon;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\PortalEngineBundle\Enum\ElasticSearchFields;

class RelationAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            'properties' => [
                'id' => [
                    'type' => AttributeType::LONG
                ],
                'type' => [
                    'type' => AttributeType::TEXT
                ],
            ]
        ];
    }
}
