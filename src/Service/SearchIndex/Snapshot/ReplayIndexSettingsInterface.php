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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;

/**
 * @internal
 */
interface ReplayIndexSettingsInterface
{
    /**
     * Disables the automatic refresh of the index for the replay.
     *
     * @return string|null the refresh_interval the index had before (null: engine default), for restore()
     *
     * @throws SnapshotImportException
     */
    public function disableRefresh(string $indexName): ?string;

    /**
     * Puts the refresh_interval back to what disableRefresh() returned.
     *
     * @throws SnapshotImportException
     */
    public function restoreRefresh(string $indexName, ?string $previousRefreshInterval): void;
}
