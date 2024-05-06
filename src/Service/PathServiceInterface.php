<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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

    public function getAllParentPaths(array $paths): array;
}
