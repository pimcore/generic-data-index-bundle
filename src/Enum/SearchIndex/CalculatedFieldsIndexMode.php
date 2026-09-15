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

enum CalculatedFieldsIndexMode: string
{
    /**
     * Execute the calculator when extracting index data (current behavior).
     */
    case LIVE = 'live';

    /**
     * Read the value stored in the object's query table (written on save) instead of
     * executing the calculator. Saves the computation on every (re)indexing.
     */
    case QUERY_STORE = 'query_store';
}
