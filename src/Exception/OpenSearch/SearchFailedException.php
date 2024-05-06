<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\GenericDataIndexBundleExceptionInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Debug\SearchInformation;
use RuntimeException;
use Throwable;

final class SearchFailedException extends RuntimeException implements GenericDataIndexBundleExceptionInterface
{
    public function __construct(
        private readonly SearchInformation $searchInformation,
        mixed $message = '',
        mixed $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getSearchInformation(): SearchInformation
    {
        return $this->searchInformation;
    }
}
