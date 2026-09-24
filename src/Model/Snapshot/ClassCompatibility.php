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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;

/**
 * @internal
 */
final readonly class ClassCompatibility
{
    public function __construct(
        private string $classId,
        private ?string $className,
        private ClassCompatibilityStatus $status,
        /** null when the manifest carries no checksum for this class at all (unverified) */
        private ?int $manifestChecksum,
        private ?int $storedChecksum,
        private ?int $computedChecksum,
    ) {
    }

    public function getClassId(): string
    {
        return $this->classId;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getStatus(): ClassCompatibilityStatus
    {
        return $this->status;
    }

    public function getManifestChecksum(): ?int
    {
        return $this->manifestChecksum;
    }

    public function getStoredChecksum(): ?int
    {
        return $this->storedChecksum;
    }

    public function getComputedChecksum(): ?int
    {
        return $this->computedChecksum;
    }
}
