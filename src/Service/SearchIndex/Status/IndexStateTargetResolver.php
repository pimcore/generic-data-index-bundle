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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Listing;

/**
 * @internal
 */
final readonly class IndexStateTargetResolver implements IndexStateTargetResolverInterface
{
    public function __construct(
        private AssetTypeAdapter $assetTypeAdapter,
        private DocumentTypeAdapter $documentTypeAdapter,
        private DataObjectTypeAdapter $dataObjectTypeAdapter,
    ) {
    }

    public function all(): array
    {
        $targets = [];
        foreach ((new Listing())->load() as $classDefinition) {
            $targets[] = IndexStateTarget::forClassDefinition($classDefinition);
        }
        $targets[] = IndexStateTarget::forScope(DeploymentReindexScope::FOLDERS);
        $targets[] = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);
        $targets[] = IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS);

        return $targets;
    }

    public function aliasName(IndexStateTarget $target): string
    {
        return match ($target->getScope()) {
            DeploymentReindexScope::ASSETS => $this->assetTypeAdapter->getAliasIndexName(),
            DeploymentReindexScope::DOCUMENTS => $this->documentTypeAdapter->getAliasIndexName(),
            DeploymentReindexScope::CLASSES,
            DeploymentReindexScope::FOLDERS => $this->dataObjectTypeAdapter->getAliasIndexName(
                $target->getClassDefinition()
            ),
        };
    }

    public function elementIndexName(IndexStateTarget $target): string
    {
        return match ($target->getScope()) {
            DeploymentReindexScope::CLASSES => (string) $target->getClassDefinition()?->getName(),
            DeploymentReindexScope::FOLDERS => IndexName::DATA_OBJECT_FOLDER->value,
            DeploymentReindexScope::ASSETS => IndexName::ASSET->value,
            DeploymentReindexScope::DOCUMENTS => IndexName::DOCUMENT->value,
        };
    }

    public function fromElementIndexName(string $elementIndexName): ?IndexStateTarget
    {
        $scope = match ($elementIndexName) {
            IndexName::ASSET->value => DeploymentReindexScope::ASSETS,
            IndexName::DOCUMENT->value => DeploymentReindexScope::DOCUMENTS,
            IndexName::DATA_OBJECT_FOLDER->value => DeploymentReindexScope::FOLDERS,
            default => null,
        };
        if ($scope !== null) {
            return IndexStateTarget::forScope($scope);
        }

        $classDefinition = ClassDefinition::getByName($elementIndexName);

        return $classDefinition === null ? null : IndexStateTarget::forClassDefinition($classDefinition);
    }

    public function fromIndexName(string $indexName): ?IndexStateTarget
    {
        $target = $this->fromElementIndexName($indexName);
        if ($target !== null) {
            return $target;
        }

        foreach ($this->all() as $candidate) {
            if ($this->aliasName($candidate) === $indexName) {
                return $candidate;
            }
        }

        return null;
    }
}
