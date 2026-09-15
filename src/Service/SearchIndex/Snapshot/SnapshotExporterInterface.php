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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;

/**
 * @internal
 */
interface SnapshotExporterInterface
{
    /**
     * @param (callable(IndexTarget, int): void)|null $onIndexExported called after each index has been
     *                                                                 written, with the exported document count
     *
     * @throws SnapshotExportException when the snapshot name already exists, the queue threshold is
     *                                  exceeded, or an index could not be exported
     */
    public function export(SnapshotStorageInterface $storage, string $name, ExportOptions $options, ?callable $onIndexExported = null): ExportResult;
}
