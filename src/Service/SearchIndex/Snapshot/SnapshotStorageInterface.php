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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;

/**
 * @internal
 */
interface SnapshotStorageInterface
{
    public const MANIFEST_FILE = 'manifest.json';

    /** @return string[] complete snapshot names, newest first by manifest created_at */
    public function listSnapshots(): array;

    public function latestSnapshotName(): ?string;

    public function hasSnapshot(string $name): bool; // manifest present

    public function readManifest(string $name): Manifest; // InvalidSnapshotException when absent/malformed

    public function writeManifest(string $name, Manifest $manifest): void;

    public function writeFile(string $name, string $file, string $localPath): void; // streams a local file in

    public function readFileToLocal(string $name, string $file, string $localPath): void; // streams a file out

    public function deleteSnapshot(string $name): void; // silent when absent

    public static function assertValidName(string $name): void; // InvalidSnapshotException
}
