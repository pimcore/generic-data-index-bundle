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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * One search index whose deployment state (mapping checksum, settings hash, data version) is tracked.
 *
 * @internal
 */
final readonly class IndexStateTarget
{
    private function __construct(
        private DeploymentReindexScope $scope,
        private ?ClassDefinition $classDefinition = null,
    ) {
    }

    public static function forClassDefinition(ClassDefinition $classDefinition): self
    {
        return new self(DeploymentReindexScope::CLASSES, $classDefinition);
    }

    public static function forScope(DeploymentReindexScope $scope): self
    {
        if ($scope === DeploymentReindexScope::CLASSES) {
            throw new \InvalidArgumentException('Class indices need their class definition');
        }

        return new self($scope);
    }

    public function getScope(): DeploymentReindexScope
    {
        return $this->scope;
    }

    public function getClassDefinition(): ?ClassDefinition
    {
        return $this->classDefinition;
    }

    public function isClassIndex(): bool
    {
        return $this->classDefinition !== null;
    }

    /**
     * Settings store key of this index, e.g. "asset" or "class_CAR".
     */
    public function getKey(): string
    {
        return match ($this->scope) {
            DeploymentReindexScope::CLASSES => 'class_' . $this->classDefinition?->getId(),
            DeploymentReindexScope::FOLDERS => 'folder',
            DeploymentReindexScope::ASSETS => 'asset',
            DeploymentReindexScope::DOCUMENTS => 'document',
        };
    }

    public function getLabel(): string
    {
        return match ($this->scope) {
            DeploymentReindexScope::CLASSES => sprintf(
                'class %s (%s)',
                $this->classDefinition?->getName(),
                $this->classDefinition?->getId()
            ),
            DeploymentReindexScope::FOLDERS => 'data object folders',
            DeploymentReindexScope::ASSETS => 'assets',
            DeploymentReindexScope::DOCUMENTS => 'documents',
        };
    }
}
