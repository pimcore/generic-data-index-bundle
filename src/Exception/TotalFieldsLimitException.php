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
 * A mapping declaring more fields than the index limit allows. Thrown before the engine is asked for it: the
 * engine checks the limit only after it has parsed the mapping into its heap, so a mapping far enough over the
 * limit takes the cluster manager down instead of answering with an error.
 *
 * @internal
 */
final class TotalFieldsLimitException extends RuntimeException implements GenericDataIndexBundleExceptionInterface
{
}
