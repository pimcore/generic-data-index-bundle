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

/**
 * @internal
 */
interface IndexProvisionerInterface
{
    /**
     * Recreates the local index for the target: deletes it if it already exists, then creates
     * it fresh with the mapping generated from the current local class/element definition.
     *
     * @throws SnapshotImportException when the target's element type is unknown
     */
    public function provision(IndexTarget $target): void;

    /**
     * Computes the class mapping checksum for the target's current local class definition, or
     * null when the target is not a class (data object) index.
     *
     * @throws SnapshotImportException when the target's element type is unknown
     */
    public function computeClassMappingChecksum(IndexTarget $target): ?int;

    /**
     * Stamps the given checksum into the settings store for the target's class.
     */
    public function stampClassMapping(IndexTarget $target, int $checksum): void;
}
