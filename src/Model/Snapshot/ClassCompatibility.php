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
        public string $classId,
        public ?string $className,
        public ClassCompatibilityStatus $status,
        /** null when the manifest carries no checksum for this class at all (unverified) */
        public ?int $manifestChecksum,
        public ?int $storedChecksum,
        public ?int $computedChecksum,
    ) {
    }
}
