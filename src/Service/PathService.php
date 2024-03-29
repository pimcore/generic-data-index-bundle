<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service;

/**
 * @internal
 */
final class PathService implements PathServiceInterface
{
    public function isSubPath(string $path, $parentPath): bool
    {
        return str_starts_with($path, $parentPath);
    }

    public function containsSubPath(string $path, array $paths): bool
    {
        foreach ($paths as $potentialSubPath) {
            if ($this->isSubPath($potentialSubPath, $path)) {
                return true;
            }
        }

        return false;
    }

    public function getContainedSubPaths(string $path, array $paths): array
    {
        $result = [];
        foreach ($paths as $potentialSubPath) {
            if ($this->isSubPath($potentialSubPath, $path)) {
                $result[] = $potentialSubPath;
            }
        }

        return $result;
    }

    public function removeSubPaths(array $paths): array
    {
        $result = [];
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
        foreach ($paths as $path) {
            $pathLevel = substr_count($path, '/');
            if ($pathLevel > $longestPathLevel) {
                $longestPathLevel = $pathLevel;
            }
        }

        return $longestPathLevel;
    }

    public function appendSlashes(array $paths): array
    {
        return array_map(static fn(string $path) => rtrim($path, '/') . '/', $paths);
    }
}