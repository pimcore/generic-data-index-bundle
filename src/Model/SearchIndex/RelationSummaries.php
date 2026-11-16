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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex;

/**
 * The related elements of a relation field, described without loading them, together with the
 * object the relations belong to (the object itself, or the ancestor it inherits them from).
 *
 * @internal
 */
final readonly class RelationSummaries
{
    /**
     * @param list<array{id: int, type: string}&array<string, mixed>> $summaries in the format of
     *        {@see \Pimcore\Model\DataObject\ClassDefinition\Data\LightweightRelationLoadingInterface}
     */
    public function __construct(
        private int $ownerId,
        private array $summaries
    ) {
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * @return list<array{id: int, type: string}&array<string, mixed>>
     */
    public function getSummaries(): array
    {
        return $this->summaries;
    }
}
