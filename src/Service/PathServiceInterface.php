<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

/**
 * @internal
 */
interface PathServiceInterface
{
    public function isSubPath(string $path, $parentPath): bool;

    public function containsSubPath(string $path, array $paths): bool;

    public function getContainedSubPaths(string $path, array $paths): array;

    public function removeSubPaths(array $paths): array;

    public function calculateLongestPathLevel(array $paths): int;

    public function appendSlashes(array $paths): array;
}
