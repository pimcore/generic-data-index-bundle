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

use Pimcore\ValueObject\Collection\ArrayOfStrings;
use Pimcore\ValueObject\String\Path;

/**
 * @internal
 */
final class PathService implements PathServiceInterface
{
    public function isSubPath(string $path, string $parentPath): bool
    {
        return $path !== $parentPath && str_starts_with($path, $parentPath);
    }

    public function containsSubPath(string $path, array $paths): bool
    {
        foreach ((new ArrayOfStrings($paths))->getValue() as $potentialSubPath) {
            if ($this->isSubPath($potentialSubPath, $path)) {
                return true;
            }
        }

        return false;
    }

    public function getContainedSubPaths(string $path, array $paths): array
    {
        $result = [];
        foreach ((new ArrayOfStrings($paths))->getValue() as $potentialSubPath) {
            if ($this->isSubPath($potentialSubPath, $path)) {
                $result[] = $potentialSubPath;
            }
        }

        return $result;
    }

    public function removeSubPaths(array $paths): array
    {
        $result = [];
        $paths = (new ArrayOfStrings($paths))->getValue();
        sort($paths);

        foreach ($paths as $path) {
            $isSubPath = false;
            foreach ($result as $existingPath) {
                if ($this->isSubPath($path, $existingPath)) {
                    $isSubPath = true;

                    break;
                }
            }

            if (!$isSubPath) {
                $result[] = $path;
            }
        }

        return $result;
    }

    public function calculateLongestPathLevel(array $paths): int
    {
        $longestPathLevel = 0;
        foreach ((new ArrayOfStrings($paths))->getValue() as $path) {
            $pathLevel = $path === '/' ? 0 : substr_count($path, '/');
            if ($pathLevel > $longestPathLevel) {
                $longestPathLevel = $pathLevel;
            }
        }

        return $longestPathLevel;
    }

    public function appendSlashes(array $paths): array
    {
        $paths = (new ArrayOfStrings($paths))->getValue();

        return array_map(static fn (string $path) => rtrim($path, '/') . '/', $paths);
    }

    public function getAllParentPaths(array $paths): array
    {
        $paths = $this->removeSubPaths($paths);
        if (count($paths) === 1 && $paths[0] === '/') {
            return [];
        }
        $result = [];
        foreach ($paths as $path) {
            $parentPath = (new Path($path))->getValue();
            while ($parentPath = $this->getParentPath($parentPath)) {
                $result[] = $parentPath;
                if ($parentPath === '/') {
                    break;
                }
            }
        }
        sort($result);

        return $result;
    }

    private function getParentPath(string $path): string
    {
        $pathParts = explode('/', $path);
        array_pop($pathParts);
        if (count($pathParts) === 1) {
            return '/';
        }

        return implode('/', $pathParts);
    }
}
