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

enum CompilerPassTag: string
{
    case DATA_OBJECT_SEARCH_INDEX_FIELD_DEFINITION =
    'pimcore.generic_data_index.data-object.search_index_field_definition';
}
