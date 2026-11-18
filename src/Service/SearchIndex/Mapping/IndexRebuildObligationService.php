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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;

/**
 * @internal
 */
final readonly class IndexRebuildObligationService implements IndexRebuildObligationServiceInterface
{
    private const KEY_SUFFIX = '_rebuild_owed';

    public function __construct(
        private SettingsStoreServiceInterface $settingsStoreService,
        private TimeServiceInterface $timeService,
    ) {
    }

    public function isOwed(IndexStateTarget $target): bool
    {
        return $this->settingsStoreService->getIndexState($this->key($target)) !== null;
    }

    public function owe(IndexStateTarget $target): void
    {
        $this->settingsStoreService->storeIndexState(
            $this->key($target),
            (string) $this->timeService->getCurrentMillisecondTimestamp()
        );
    }

    public function settle(IndexStateTarget $target): void
    {
        $this->settingsStoreService->removeIndexState($this->key($target));
    }

    private function key(IndexStateTarget $target): string
    {
        return $target->getKey() . self::KEY_SUFFIX;
    }
}
