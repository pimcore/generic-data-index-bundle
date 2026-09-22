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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot;

/**
 * The index settings ReplayIndexSettings changes for a replay, as they were before: null means
 * the setting was not set on the index (engine default) and is reset to that on restore.
 *
 * @internal
 */
final readonly class IndexSettingsBackup
{
    public function __construct(
        public ?string $refreshInterval,
        public ?string $translogDurability,
    ) {
    }
}
