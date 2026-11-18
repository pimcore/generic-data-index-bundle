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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;

/**
 * @internal
 */
final readonly class LatestLanguageChange implements LatestLanguageChangeInterface
{
    private const KEY = 'language_change_requested';

    public function __construct(
        private SettingsStoreServiceInterface $settingsStoreService,
    ) {
    }

    public function record(array $validLanguages): void
    {
        $this->settingsStoreService->storeIndexState(self::KEY, json_encode(array_values($validLanguages)));
    }

    public function isLatest(array $validLanguages): bool
    {
        $latest = $this->settingsStoreService->getIndexState(self::KEY);
        if ($latest === null) {
            return true;
        }

        return json_decode($latest, true) === array_values($validLanguages);
    }
}
