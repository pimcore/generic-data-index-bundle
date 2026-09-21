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
 * page stays within the byte budget, because a fixed document count alone is not safe: the size
 * of an indexed document is unknown in advance, and one page of 1000 large documents can exhaust
 * PHP's memory limit on its own.
 *
 * The first page of an index requests a single document, so that a page can never exceed the
 * budget before anything is known about document size. Every following page is sized from the
 * larger of two estimates: the average over all documents written so far, and the average of the
 * most recent page. The second one is what makes a run of larger documents shrink the next page
 * immediately instead of waiting for the cumulative average to catch up; a single outlier, which
 * moves neither average much, does not collapse the page size.
 *
 * The budget is a target, not a guarantee: the search engine cannot be asked for "at most N
 * bytes", so a page that is larger than every page before it is only corrected afterwards.
 *
 * @internal
 */
final class PageSizer
{
    /**
     * Documents requested for the first page of an index, before any size is known.
     */
    public const PROBE_SIZE = 1;

    private int $totalDocuments = 0;

    private int $totalBytes = 0;

    private int $lastPageDocuments = 0;

    private int $lastPageBytes = 0;

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
     * @param int $documents documents the page returned
     * @param int $bytes raw (uncompressed) JSON bytes those documents took
     */
    public function recordPage(int $documents, int $bytes): void
    {
        if ($documents < 1) {
            // An empty page carries no size information and must not reset the estimate.
            return;
        }

        $this->totalDocuments += $documents;
        $this->totalBytes += $bytes;
        $this->lastPageDocuments = $documents;
        $this->lastPageBytes = $bytes;
    }

    public function nextPageSize(): int
    {
        if ($this->totalDocuments < 1) {
            return self::PROBE_SIZE;
        }

        $estimatedDocumentBytes = max(
            1,
            intdiv($this->totalBytes, $this->totalDocuments),
            intdiv($this->lastPageBytes, $this->lastPageDocuments),
        );

        return max(1, min($this->maxDocuments, intdiv($this->byteBudget, $estimatedDocumentBytes)));
    }
}
