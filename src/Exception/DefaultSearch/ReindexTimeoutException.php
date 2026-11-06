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

namespace Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch;

/**
 * The copy ran out of the budget of reindex_settings (max_polls × poll_interval): the engine task was still running
 * when the last status poll was done. A repetition with the same budget runs out of it as well.
 *
 * @internal
 */
final class ReindexTimeoutException extends ReindexFailedException
{
}
