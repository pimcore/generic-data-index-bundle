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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexRebuildMarker;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;

/**
 * @internal
 */
final readonly class IndexRebuildStateService implements IndexRebuildStateServiceInterface
{
    private const KEY_SUFFIX = '_rebuild_queued_at';

    public function __construct(
        private SettingsStoreServiceInterface $settingsStoreService,
        private TimeServiceInterface $timeService,
    ) {
    }

    public function markEnqueuing(IndexStateTarget $target): IndexRebuildMarker
    {
        // The token keeps two rebuilds queued in the same millisecond apart: each conditional transition below
        // matches the whole stored value.
        $marker = new IndexRebuildMarker(
            $this->timeService->getCurrentMillisecondTimestamp(),
            false,
            bin2hex(random_bytes(8))
        );
        $this->settingsStoreService->storeIndexState($this->key($target), $marker->toStoredValue());

        return $marker;
    }

    public function markEnqueued(IndexStateTarget $target, IndexRebuildMarker $marker): bool
    {
        return $this->settingsStoreService->replaceIndexState(
            $this->key($target),
            $marker->toStoredValue(),
            $marker->withEnqueued()->toStoredValue()
        );
    }

    public function getMarker(IndexStateTarget $target): ?IndexRebuildMarker
    {
        $value = $this->settingsStoreService->getIndexState($this->key($target));

        return $value === null ? null : IndexRebuildMarker::fromStoredValue($value);
    }

    public function claim(IndexStateTarget $target, IndexRebuildMarker $marker): bool
    {
        return $this->settingsStoreService->removeIndexStateIfEquals($this->key($target), $marker->toStoredValue());
    }

    private function key(IndexStateTarget $target): string
    {
        return $target->getKey() . self::KEY_SUFFIX;
    }
}
