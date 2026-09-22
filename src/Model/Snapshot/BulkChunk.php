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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot;

/**
 * One ready-made NDJSON bulk body on disk (index action + document line per document), with
 * the number of documents it holds and the raw snapshot bytes they took. Whoever sends it
 * deletes the file.
 *
 * @internal
 */
final readonly class BulkChunk
{
    public function __construct(
        public string $path,
        public int $documents,
        public int $bytes,
    ) {
    }
}
