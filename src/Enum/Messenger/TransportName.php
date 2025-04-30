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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\Messenger;

/**
 * @internal
 */
enum TransportName: string
{
    case INDEX_QUEUE = 'pimcore_generic_data_index_queue';
    case SYNC = 'pimcore_generic_data_index_sync';
}
