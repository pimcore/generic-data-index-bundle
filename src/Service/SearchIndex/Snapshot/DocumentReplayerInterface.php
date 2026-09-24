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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;

/**
 * @internal
 */
interface DocumentReplayerInterface
{
    /**
     * Streams every document of a downloaded snapshot file into the bulk API of the given index.
     *
     * @param string $localFile downloaded, checksum-verified snapshot file
     *
     * @throws SnapshotImportException
     */
    public function replay(IndexTarget $target, ManifestIndex $entry, string $localFile): void;
}
