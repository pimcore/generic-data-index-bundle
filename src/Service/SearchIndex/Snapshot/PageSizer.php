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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use InvalidArgumentException;

/**
 * Decides how many documents the exporter requests per search_after page.
 *
 * The configured maximum is a ceiling. Below it, the page shrinks so that the raw JSON of one
 * page stays within the byte budget: the first page is a small probe, every following page is
 * sized from the running average of what has been written for the current index so far.
 * A fixed document count alone is not safe, because the size of an indexed document is unknown
 * in advance and one page of 1000 large documents can exhaust PHP's memory limit on its own.
 *
 * @internal
 */
final class PageSizer
{
    public const PROBE_SIZE = 50;

    public function __construct(
        private readonly int $maxDocuments,
        private readonly int $byteBudget,
    ) {
        if ($maxDocuments < 1) {
            throw new InvalidArgumentException('The maximum page size must be at least 1 document');
        }
        if ($byteBudget < 1) {
            throw new InvalidArgumentException('The page byte budget must be at least 1 byte');
        }
    }

    /**
     * @param int $documentsWritten documents exported so far for the current index
     * @param int $bytesWritten raw (uncompressed) JSON bytes those documents took
     */
    public function nextPageSize(int $documentsWritten, int $bytesWritten): int
    {
        if ($documentsWritten < 1) {
            return min(self::PROBE_SIZE, $this->maxDocuments);
        }

        $averageBytes = max(1, intdiv($bytesWritten, $documentsWritten));
        $byBudget = intdiv($this->byteBudget, $averageBytes);

        return max(1, min($this->maxDocuments, $byBudget));
    }
}
