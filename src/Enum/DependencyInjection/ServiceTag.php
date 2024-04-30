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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection;

/**
 * @internal
 */
enum ServiceTag: string
{
    case DATA_OBJECT_SEARCH_INDEX_FIELD_DEFINITION =
    'pimcore.generic_data_index.data-object.search_index_field_definition';
    case ASSET_SEARCH_INDEX_FIELD_DEFINITION = 'pimcore.generic_data_index.asset.search_index_field_definition';
    case SEARCH_MODIFIER_HANDLER = 'pimcore.generic_data_index.search_modifier_handler';
    case ASSET_TYPE_SERIALIZATION_HANDLER = 'pimcore.generic_data_index.asset_type_serialization_handler';
    case DATA_OBJECT_TYPE_SERIALIZATION_HANDLER = 'pimcore.generic_data_index.data_object_type_serialization_handler';
    case DOCUMENT_TYPE_SERIALIZATION_HANDLER = 'pimcore.generic_data_index.document_type_serialization_handler';
    case ASSET_MAPPING_PROVIDER = 'pimcore.generic_data_index.asset.mapping_provider';
    case PQL_FIELD_NAME_TRANSFORMER = 'pimcore.generic_data_index.pql_field_name_transformer';
}
