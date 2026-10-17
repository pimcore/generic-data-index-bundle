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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping;

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingFieldChangeAction;

/**
 * The fields whose mapping differs between the live index and the new mapping,
 * classified into fields kept in the copy and fields stripped from it.
 *
 * @internal
 */
final readonly class MappingDiff
{
    /** @var MappingFieldChange[] */
    private array $changes;

    /** @var string[][] */
    private array $removedPathSegments;

    /**
     * @param MappingFieldChange[] $changes
     * @param string[][] $removedPathSegments fields of the live mapping the new mapping no longer has
     */
    public function __construct(array $changes, array $removedPathSegments = [])
    {
        foreach ($removedPathSegments as $segments) {
            if ($segments === []) {
                throw new InvalidArgumentException('A removed field path needs at least one segment');
            }
        }
        $this->removedPathSegments = array_values($removedPathSegments);
        usort(
            $changes,
            static fn (MappingFieldChange $a, MappingFieldChange $b): int => strcmp($a->getPath(), $b->getPath())
        );
        $this->changes = $changes;
    }

    /**
     * @return MappingFieldChange[]
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Fields of the live mapping the new mapping no longer has. They are no changes: the copy keeps
     * their values and the engine maps them again.
     *
     * @return string[][]
     */
    public function getRemovedPathSegments(): array
    {
        return $this->removedPathSegments;
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public function hasStripPaths(): bool
    {
        return $this->getStripPaths() !== [];
    }

    /**
     * @return string[]
     */
    public function getStripPaths(): array
    {
        return $this->pathsFor(MappingFieldChangeAction::STRIP);
    }

    /**
     * @return string[][] path segments of the stripped fields, for the copy script
     */
    public function getStripPathSegments(): array
    {
        $paths = [];
        foreach ($this->changes as $change) {
            if ($change->getAction() === MappingFieldChangeAction::STRIP) {
                $paths[] = $change->getPathSegments();
            }
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    public function getKeptPaths(): array
    {
        return $this->pathsFor(MappingFieldChangeAction::KEEP);
    }

    /**
     * @return string[]
     */
    private function pathsFor(MappingFieldChangeAction $action): array
    {
        $paths = [];
        foreach ($this->changes as $change) {
            if ($change->getAction() === $action) {
                $paths[] = $change->getPath();
            }
        }

        return $paths;
    }
}
