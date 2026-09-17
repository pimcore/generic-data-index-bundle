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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;

/**
 * @internal
 */
interface SnapshotIndexResolverInterface
{
    /**
     * @return IndexTarget[] folder index, one per class definition, asset, document
     */
    public function resolveAll(): array;

    /**
     * null when the manifest entry has no local counterpart (class missing, unknown element type)
     */
    public function resolveManifestIndex(ManifestIndex $index): ?IndexTarget;

    public function shortName(string $aliasName): string;
}
