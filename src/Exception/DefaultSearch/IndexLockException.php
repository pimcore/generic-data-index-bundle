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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\GenericDataIndexBundleExceptionInterface;
use RuntimeException;

/**
 * The lock of an index could not be taken from the lock store, so its mapping update did not start.
 */
final class IndexLockException extends RuntimeException implements GenericDataIndexBundleExceptionInterface
{
}
