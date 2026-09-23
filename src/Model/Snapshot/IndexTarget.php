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
    private IndexIdentity $identity;

    public function __construct(
        private string $shortName,
        private string $aliasName,
        private string $elementType,
        private ?ClassDefinition $classDefinition = null,
    ) {
        $this->identity = new IndexIdentity($elementType, $classDefinition?->getId());
    }

    public function getIdentity(): IndexIdentity
    {
        return $this->identity;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function getAliasName(): string
    {
        return $this->aliasName;
    }

    public function getElementType(): string
    {
        return $this->elementType;
    }

    public function getClassDefinition(): ?ClassDefinition
    {
        return $this->classDefinition;
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
