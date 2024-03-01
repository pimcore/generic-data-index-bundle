<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Debug\SearchInformation;
use RuntimeException;
use Throwable;

final class SearchFailedException extends RuntimeException
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
