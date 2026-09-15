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
     * @param callable(ImportedIndex): void|null $onIndexImported called after each index has been replayed
     *
     * @throws SnapshotIncompatibleException when the class definitions do not match and $options->force is false
     * @throws SnapshotImportException when the snapshot is corrupted, an `only` name is unknown, or an index
     *                                  could not be provisioned or replayed
     */
    public function import(
        SnapshotStorageInterface $storage,
        string $name,
        ImportOptions $options,
        ?callable $onIndexImported = null
    ): ImportResult;
}
