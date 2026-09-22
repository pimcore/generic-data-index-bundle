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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexSettingsBackup;

/**
 * @internal
 */
interface ReplayIndexSettingsInterface
{
    /**
     * Switches an index into bulk-loading mode for the replay: no automatic refresh and an
     * asynchronous translog. Returns the values the index had before, for restore().
     *
     * @throws SnapshotImportException
     */
    public function apply(string $indexName): IndexSettingsBackup;

    /**
     * Puts the settings changed by apply() back to what the index had before: the installation's
     * configured values where it had any, the engine defaults otherwise.
     *
     * @throws SnapshotImportException
     */
    public function restore(string $indexName, IndexSettingsBackup $backup): void;
}
