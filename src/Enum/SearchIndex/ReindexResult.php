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
 * Outcome of a native reindex into a freshly created index version.
 *
 * MAPPING_INCOMPATIBLE is an expected alternative outcome, not an error: the
 * existing documents cannot be indexed into the new mapping (e.g. after a field
 * type change), so the caller may decide to recreate the index and re-populate
 * it from the primary data source. Genuine errors (unreachable cluster,
 * timeouts, rejected requests) are thrown as exceptions instead — they must
 * never be answered with index recreation.
 *
 * @internal
 */
enum ReindexResult
{
    case SUCCESS;
    case MAPPING_INCOMPATIBLE;
}
