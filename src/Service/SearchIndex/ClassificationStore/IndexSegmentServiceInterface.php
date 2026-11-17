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
 * Answers which path segment a classification store group or key is addressed by in the index.
 *
 * A group and a key are indexed under their identifier, and an entity that carries none under its
 * name - normalized, because the index splits a field path on dots. Addressing the wrong segment
 * returns an empty result rather than an error, so a caller that builds the path itself reads a
 * renamed or a legacy entity as an absent value. This is the resolution the mapping and the
 * document perform, exposed for the callers that have to address it - the `$group` argument and the
 * sub-modifier field name of
 * {@see \Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\ClassificationStoreFilter}.
 *
 * What this service does not answer is the collision drop: two entities of one store whose names
 * normalize to the same segment keep the first one and drop the second, positionally, out of the
 * listing the index is being built from. That is a configuration defect the index reports, it
 * cannot arise between identifiers, and the position is not the same on the mapping and the
 * document path, so it is not an answer a read-side caller can be given.
 *
 * This interface is deliberately NOT `@internal`, unlike the implementation behind it: addressing a
 * classification store path is exactly the thing a bundle outside this one cannot be left to work out
 * for itself, so the contract is public and the class that answers it is not.
 *
 * @see KeyLocaleServiceInterface for the language node the same key is indexed under
 */
interface IndexSegmentServiceInterface
{
    /**
     * The segment the group is addressed by, null for a group this installation does not have and
     * for one whose name leaves no usable segment, which is a group the index holds nothing of.
     */
    public function getGroupSegment(int $groupId): ?string;

    /**
     * The segment the key is addressed by, or - for a declared variant of a `multiValue` key - that
     * of the `<key>_<variant>` sibling the variant is written to. Null under the conditions
     * {@see self::getGroupSegment()} answers null, and for a variant name that leaves no segment.
     */
    public function getKeySegment(int $keyId, ?string $variant = null): ?string;

    /**
     * Every entry the keys of one group are written under in the document, as the segment that
     * addresses the entry => what stands behind it. The answer to "what does this group's document
     * actually contain", for a caller that has to read or type those entries: enumerating the group's
     * keys and naming them itself is the same rule implemented a second time, and a second
     * implementation reads a renamed, a legacy or a `multiValue` key as an absent value.
     *
     * The entries are the ones {@see self::getKeySegment()} answers for, in the order and with the
     * exclusions the document applies: a key the index does not write contributes nothing - one marked
     * as not indexed, one whose value type the index cannot type, one the store's engine cannot hold,
     * and one whose name leaves no segment. A `multiValue` key contributes one `<key>_<variant>`
     * sibling per declared variant, plus its bare entry unless it declines the unqualified value. Two
     * keys of one group whose names normalize to the same segment own one entry between them, the
     * first in the listing, exactly as the document writes it.
     *
     * An empty array is the honest answer for a group this installation does not have and for one
     * whose keys are all excluded; the two are not distinguished, because neither is addressable.
     *
     * @return array<string, ClassificationStoreEntry> keyed by the document segment
     */
    public function getGroupEntries(int $groupId): array;

    /**
     * A name as the index uses it for a path segment: empty components dropped, null for a name
     * that has nothing else. Exposed because the mapping and the document apply it to names this
     * service is not asked about, not because a caller addressing an entity should apply it itself.
     */
    public function normalizeSegment(string $name): ?string;
}
