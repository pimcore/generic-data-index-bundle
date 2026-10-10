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
use Pimcore\Model\User;

/**
 * Runtime cache handling for byId(). Search results are cached per element regardless of the user and the
 * permission they were loaded with, so for restricted users a cached item is only returned once a view search
 * for this user found the element.
 *
 * @internal
 */
trait ByIdRuntimeCacheTrait
{
    private function loadCachedByIdResult(string $cacheKey, ?User $user): mixed
    {
        try {
            $searchResult = $this->runtimeCacheResolver->load($cacheKey);
        } catch (Exception) {
            return null;
        }

        if ($searchResult === null || !$this->isRestrictedUser($user)) {
            return $searchResult;
        }

        $viewCacheKey = $this->getViewCacheKey($cacheKey, $user);
        if (!$this->runtimeCacheResolver->isRegistered($viewCacheKey)) {
            return null;
        }

        return $this->runtimeCacheResolver->load($viewCacheKey) === true ? $searchResult : null;
    }

    private function rememberByIdResult(string $cacheKey, ?User $user, ?object $searchResult): void
    {
        if ($this->isRestrictedUser($user)) {
            $this->runtimeCacheResolver->save(
                $searchResult !== null,
                $this->getViewCacheKey($cacheKey, $user)
            );
        }
    }

    /**
     * @phpstan-assert-if-true User $user
     */
    private function isRestrictedUser(?User $user): bool
    {
        return $user !== null && !$user->isAdmin();
    }

    private function getViewCacheKey(string $cacheKey, User $user): string
    {
        return $cacheKey . '_view_' . $user->getId();
    }
}
