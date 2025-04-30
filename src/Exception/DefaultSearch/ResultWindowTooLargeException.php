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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Debug\SearchInformation;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
final class ResultWindowTooLargeException extends RuntimeException implements GenericDataIndexBundleExceptionInterface
{
    public function __construct(
        private readonly SearchInformation $searchInformation,
        mixed $message = '',
        mixed $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getSearchInformation(): SearchInformation
    {
        return $this->searchInformation;
    }
}
