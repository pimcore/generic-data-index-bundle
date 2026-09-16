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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotIncompatibleException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportedIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportResult;

/**
 * @internal
 */
interface SnapshotImporterInterface
{
    /**
     * Replays a snapshot's indices into the local search engine, in two phases. First, every
     * planned index's file is downloaded and its checksum verified — before any index is
     * touched, not just before the index it belongs to — so a corrupted or missing file
     * anywhere in the plan is caught while every live index, including ones earlier in the
     * plan, is still untouched. Only once every planned file has verified does the second
     * phase provision (delete and recreate) and replay each index in turn.
     *
     * The apply phase is not atomic across indices: if replaying one index fails — including a
     * replayed document count that doesn't match the manifest's — every index replayed before it
     * is complete; the failing index itself has already been recreated and is left partially
     * filled; indices not yet reached are untouched (their previous contents, if any, are
     * unaffected). Re-running the import repairs a partial import, since every index is
     * provisioned from scratch again.
     *
     * @param (callable(ImportedIndex): void)|null $onIndexImported called after each index has been replayed
     *
     * @throws InvalidSnapshotException when the snapshot's manifest itself cannot be read
     * @throws SnapshotIncompatibleException when the class definitions do not match and $options->force is false
     * @throws SnapshotImportException when an index file has an unsafe or unexpected name, an
     *                                  index file is corrupted, truncated, or could not be read
     *                                  from storage, or an index could not be replayed, including
     *                                  its replayed document count not matching the manifest's
     */
    public function import(
        SnapshotStorageInterface $storage,
        string $name,
        ImportOptions $options,
        ?callable $onIndexImported = null
    ): ImportResult;
}
