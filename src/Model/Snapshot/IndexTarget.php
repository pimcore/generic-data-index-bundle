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

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final readonly class IndexTarget
{
    public IndexIdentity $identity;

    public function __construct(
        public string $shortName,
        public string $aliasName,
        public string $elementType,
        public ?ClassDefinition $classDefinition = null,
    ) {
        $this->identity = new IndexIdentity($elementType, $classDefinition?->getId());
    }

    public function isClassIndex(): bool
    {
        return $this->classDefinition !== null;
    }

    public function getClassId(): ?string
    {
        return $this->classDefinition?->getId();
    }
}
