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
 * One line of a snapshot file exactly as stored, plus its raw byte size (including the newline).
 * The importer sends it into the bulk API unchanged, without a decode/encode round trip.
 *
 * @internal
 */
final readonly class RawDocumentLine
{
    public function __construct(
        public string $json,
        public int $bytes,
    ) {
    }
}
