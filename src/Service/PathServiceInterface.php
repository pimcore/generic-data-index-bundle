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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

/**
 * @internal
 */
interface PathServiceInterface
{
    public function isSubPath(string $path, string $parentPath): bool;

    public function containsSubPath(string $path, array $paths): bool;

    public function getContainedSubPaths(string $path, array $paths): array;

    public function removeSubPaths(array $paths): array;

    public function calculateLongestPathLevel(array $paths): int;

    public function appendSlashes(array $paths): array;

    public function getAllParentPaths(array $paths, bool $removeSubPaths = true): array;
}
