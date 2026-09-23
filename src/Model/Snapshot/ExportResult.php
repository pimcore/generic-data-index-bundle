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
 * @internal
 */
final readonly class ExportResult
{
    public function __construct(
        private string $name,
        private Manifest $manifest,
        private bool $dryRun,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}
