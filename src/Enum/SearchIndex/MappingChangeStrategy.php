<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

/**
 * How a mapping change is applied to an index whose documents were written under the old mapping
 * (configuration `index_service.reindex_settings.mapping_change_strategy`).
 *
 * - RECREATE: the documents are copied as they are; when their old values do not fit the new mapping, the
 *   index is recreated empty and rebuilt from the database. The behaviour before the copy strategy existed.
 * - COPY: fields whose old values do not fit are left out of the copy, so every document stays in the index
 *   while the rebuild from the database refills them; the index is recreated only as a last resort.
 *
 * @internal
 */
enum MappingChangeStrategy: string
{
    case RECREATE = 'recreate';
    case COPY = 'copy';
}
