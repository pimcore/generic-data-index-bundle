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
final readonly class CompatibilityReport
{
    /**
     * @param ClassCompatibility[] $classes
     * @param string[] $missingInManifest class names
     */
    public function __construct(
        public array $classes,
        public array $missingInManifest,
    ) {
    }

    public function isCompatible(): bool
    {
        return $this->incompatibleClassIds() === [];
    }

    /**
     * @return string[]
     */
    public function incompatibleClassIds(): array
    {
        $ids = [];
        foreach ($this->classes as $class) {
            if ($class->status === ClassCompatibilityStatus::INCOMPATIBLE) {
                $ids[] = $class->classId;
            }
        }

        return $ids;
    }

    public function statusOf(string $classId): ?ClassCompatibilityStatus
    {
        foreach ($this->classes as $class) {
            if ($class->classId === $classId) {
                return $class->status;
            }
        }

        return null;
    }
}
