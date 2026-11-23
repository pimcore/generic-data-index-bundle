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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search;

/**
 * Which of the indices a search reads declare a field path, and which do not.
 *
 * A search names one index alias, and an alias can be backed by more than one concrete index: a
 * classless data object search uses the global alias, so every class index is behind it. Whether a
 * path is declared is then a per-index answer and not one answer, and collapsing it loses exactly the
 * case the distinction exists for - a key that is declared under a language node in one class index
 * and only under the non-localized node in another.
 *
 * @internal
 */
final readonly class MappedFieldPathDeclaration
{
    /**
     * @param string[] $declaringIndices concrete indices whose mapping declares the path
     * @param string[] $omittingIndices  concrete indices that were read and do not declare it
     */
    private function __construct(
        private array $declaringIndices,
        private array $omittingIndices,
    ) {
    }

    /**
     * @param string[] $declaringIndices
     * @param string[] $omittingIndices
     */
    public static function fromIndices(array $declaringIndices, array $omittingIndices): self
    {
        return new self($declaringIndices, $omittingIndices);
    }

    /**
     * No index was read, so the mapping says nothing about the path. Both predicates below are false,
     * which leaves the caller building the query it would have built anyway rather than suppressing or
     * narrowing a filter on a guess.
     */
    public static function unanswered(): self
    {
        return new self([], []);
    }

    /**
     * Every index behind the alias was read and none of them declares the path, so no document the
     * search can reach holds a value there.
     */
    public function isUndeclaredEverywhere(): bool
    {
        return $this->declaringIndices === [] && $this->omittingIndices !== [];
    }

    /**
     * Some but not all of the indices declare the path. A query about the path is only meaningful in
     * the ones that do, so it has to name them.
     */
    public function isDeclaredByPartOfTheIndices(): bool
    {
        return $this->declaringIndices !== [] && $this->omittingIndices !== [];
    }

    /**
     * @return string[]
     */
    public function getDeclaringIndices(): array
    {
        return $this->declaringIndices;
    }
}
