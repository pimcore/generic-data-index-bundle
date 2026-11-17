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

namespace Pimcore\Bundle\GenericDataIndexBundle\Exception;

use RuntimeException;

/**
 * The engine refused to create an index, mapping and all. Carries the explanation of the refusal, so a limit
 * the mapping exceeds arrives with what to do about it instead of as a raw client error.
 *
 * @internal
 */
final class IndexCreationFailedException extends RuntimeException implements GenericDataIndexBundleExceptionInterface
{
}
