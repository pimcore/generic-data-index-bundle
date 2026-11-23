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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore;

/**
 * What stands behind one entry of a classification store group in the document, as
 * {@see DocumentEntryServiceInterface::getGroupEntries()} answers it.
 *
 * The segment an entry is addressed by is the array key it is answered under, not a property here: an
 * entry is only ever handed out together with the segment that reaches it, so a caller cannot hold one
 * without the other and re-derive the naming rule this bundle owns.
 *
 * The three properties are the three questions a caller outside the bundle has about an entry it found
 * in the document: which key wrote it, which of that key's slots it holds, and what to call it in a
 * rendering that a ULID identifier would otherwise fill.
 */
final readonly class ClassificationStoreEntry
{
    /**
     * @param int $keyId the key the entry belongs to - the same id for the bare entry and for every
     *                   one of its variant siblings, so a caller typing the entry types it once
     * @param string|null $variant the declared variant a `<key>_<variant>` sibling holds, null for the
     *                             bare entry, which is the unqualified slot of the key
     * @param string $name the key's configured name, for a caller that renders a label: the segment is
     *                     the key's identifier wherever it has one, which is not a name to show anyone
     */
    public function __construct(
        public int $keyId,
        public ?string $variant,
        public string $name,
    ) {
    }
}
