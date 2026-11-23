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
 * Answers which entries the document holds for a classification store group.
 *
 * {@see IndexSegmentServiceInterface} answers the segment of one entity a caller already knows about;
 * this answers the set, for a caller that has to read or type a whole group without enumerating its
 * keys itself. Enumerating them is the same rule implemented a second time, and a second
 * implementation reads a renamed, a legacy or a `multiValue` key as an absent value.
 *
 * This is a capability of the service behind {@see IndexSegmentServiceInterface} and not a part of it:
 * that contract is published, so a bundle outside this one may implement, decorate or stub it, and an
 * abstract method added to it would stop every such implementation from satisfying it. A caller that
 * needs both asks for both.
 *
 * Not `@internal`, for the reason the segment contract is not: addressing a classification store path
 * is the thing a bundle outside this one cannot be left to work out for itself.
 */
interface DocumentEntryServiceInterface
{
    /**
     * Every entry the keys of one group are written under in the document, as the segment that
     * addresses the entry => what stands behind it.
     *
     * The entries are the ones {@see IndexSegmentServiceInterface::getKeySegment()} answers for, in the
     * order and with the exclusions the document applies: a key the index does not write contributes
     * nothing - one marked as not indexed, one whose value type the index cannot type, one the store's
     * engine cannot hold, and one whose name leaves no segment. A `multiValue` key contributes one
     * `<key>_<variant>` sibling per declared variant, plus its bare entry unless it declines the
     * unqualified value. Two keys of one group whose names normalize to the same segment own one entry
     * between them, the first in the listing, exactly as the document writes it.
     *
     * An empty array is the honest answer for a group the document has no node for at all - one this
     * installation does not have, one whose name leaves no segment, and one whose keys are all
     * excluded; the three are not distinguished, because none of them is addressable.
     *
     * What this cannot answer is the group the document drops because a SIBLING group of the same store
     * took its segment first: that is a property of the listing and not of the group, and this is asked
     * about one group. A caller enumerating a store rather than a group reads the groups through
     * {@see IndexSegmentServiceInterface::getGroupSegment()} and drops a repeated segment itself.
     *
     * @return array<string, ClassificationStoreEntry> keyed by the document segment
     */
    public function getGroupEntries(int $groupId): array;
}
