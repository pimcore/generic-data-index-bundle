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
     * A name as the index uses it for a path segment: empty components dropped, null for a name
     * that has nothing else. Exposed because the mapping and the document apply it to names this
     * service is not asked about, not because a caller addressing an entity should apply it itself.
     */
    public function normalizeSegment(string $name): ?string;
}
