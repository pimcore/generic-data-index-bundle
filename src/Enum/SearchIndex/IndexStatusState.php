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
 * How complete an index is, derived from the facts in IndexStatus.
 */
enum IndexStatusState: string
{
    /** The index (its alias) doesn't exist. */
    case MISSING = 'missing';

    /** An odd/even copy is running; search still shows the old state. */
    case COPYING = 'copying';

    /** A rebuild from the database is queued and not yet processed. */
    case REBUILDING = 'rebuilding';

    /** Documents still carry the state of a copy that left out fields; the rebuild restores them. */
    case DEGRADED = 'degraded';

    /** Documents are missing and no rebuild is queued: the index needs a manual rebuild. */
    case INCOMPLETE = 'incomplete';

    /** All elements are indexed; waiting queue entries are the normal lag of asynchronous indexing. */
    case COMPLETE = 'complete';
}
