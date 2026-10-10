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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Traits;

use Exception;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;

/**
 * Runtime cache handling for byId(). Search results are cached per element regardless of the user and the
 * permission they were loaded with, so for restricted users a cached item is only returned if it is the item
 * that a view search for this user returned.
 *
 * @internal
 */
trait ByIdRuntimeCacheTrait
{
    /**
     * @template T of object
     *
     * @param callable(): ?T $search
     *
     * @return T|null
     */
    private function findByIdWithRuntimeCache(
        RuntimeCacheResolverInterface $runtimeCache,
        string $cacheKey,
        ?User $user,
        bool $forceReload,
        callable $search
    ): ?object {
        if (!$forceReload) {
            $searchResult = $this->loadCachedByIdResult($runtimeCache, $cacheKey, $user);
            if ($searchResult !== null) {
                return $searchResult;
            }
        }

        $searchResult = $search();
        if ($forceReload) {
            $runtimeCache->save($searchResult, $cacheKey);
        }

        $viewCacheKey = $this->getViewCacheKey($cacheKey, $user);
        if ($viewCacheKey !== null) {
            $runtimeCache->save($searchResult, $viewCacheKey);
        }

        return $searchResult;
    }

    private function loadCachedByIdResult(
        RuntimeCacheResolverInterface $runtimeCache,
        string $cacheKey,
        ?User $user
    ): mixed {
        try {
            $searchResult = $runtimeCache->load($cacheKey);
            if ($searchResult === null || $user === null || $user->isAdmin()) {
                return $searchResult;
            }

            $viewCacheKey = $this->getViewCacheKey($cacheKey, $user);
            if ($viewCacheKey === null
                || !$runtimeCache->isRegistered($viewCacheKey)
                || $runtimeCache->load($viewCacheKey) !== $searchResult
            ) {
                return null;
            }

            return $searchResult;
        } catch (Exception) {
            return null;
        }
    }

    private function getViewCacheKey(string $cacheKey, ?User $user): ?string
    {
        if ($user === null || $user->isAdmin() || $user->getId() === null) {
            return null;
        }

        return $cacheKey . '_view_' . $user->getId();
    }
}
