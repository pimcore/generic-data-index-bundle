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
 * One decoded document of a snapshot file together with the raw size of its NDJSON line,
 * so the importer can bound its bulk requests by bytes as well as by document count.
 *
 * @internal
 */
final readonly class DocumentLine
{
    public function __construct(
        public array $document,
        public int $bytes,
    ) {
    }
}
