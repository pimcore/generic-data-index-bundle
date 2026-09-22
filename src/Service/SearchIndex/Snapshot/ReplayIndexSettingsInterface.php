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
     * Switches an index into bulk-loading mode for the replay: no automatic refresh and an
     * asynchronous translog. Must be paired with restore().
     *
     * @throws SnapshotImportException
     */
    public function apply(string $indexName): void;

    /**
     * Puts the settings changed by apply() back to the engine defaults.
     *
     * @throws SnapshotImportException
     */
    public function restore(string $indexName): void;
}
