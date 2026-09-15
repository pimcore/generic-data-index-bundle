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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final class SnapshotIndexResolver implements SnapshotIndexResolverInterface
{
    public function __construct(
        private readonly DataObjectTypeAdapter $dataObjectTypeAdapter,
        private readonly AssetTypeAdapter $assetTypeAdapter,
        private readonly DocumentTypeAdapter $documentTypeAdapter,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {
    }

    public function resolveAll(): array
    {
        $targets = [$this->target($this->dataObjectTypeAdapter->getAliasIndexName(), $this->dataObjectTypeAdapter->getElementType())];

        foreach ((new ClassDefinition\Listing())->load() as $classDefinition) {
            $targets[] = $this->target(
                $this->dataObjectTypeAdapter->getAliasIndexName($classDefinition),
                $this->dataObjectTypeAdapter->getElementType(),
                $classDefinition
            );
        }

        $targets[] = $this->target($this->assetTypeAdapter->getAliasIndexName(), $this->assetTypeAdapter->getElementType());
        $targets[] = $this->target($this->documentTypeAdapter->getAliasIndexName(), $this->documentTypeAdapter->getElementType());

        return $targets;
    }

    public function resolveManifestIndex(ManifestIndex $index): ?IndexTarget
    {
        if ($index->elementType === $this->assetTypeAdapter->getElementType()) {
            return $this->target($this->assetTypeAdapter->getAliasIndexName(), $index->elementType);
        }
        if ($index->elementType === $this->documentTypeAdapter->getElementType()) {
            return $this->target($this->documentTypeAdapter->getAliasIndexName(), $index->elementType);
        }
        if ($index->elementType !== $this->dataObjectTypeAdapter->getElementType()) {
            return null;
        }
        if ($index->classId === null) {
            return $this->target($this->dataObjectTypeAdapter->getAliasIndexName(), $index->elementType);
        }
        $classDefinition = ClassDefinition::getById($index->classId);
        if ($classDefinition === null) {
            return null;
        }

        return $this->target($this->dataObjectTypeAdapter->getAliasIndexName($classDefinition), $index->elementType, $classDefinition);
    }

    public function shortName(string $aliasName): string
    {
        $prefix = $this->searchIndexConfigService->getIndexPrefix();

        return $prefix !== '' && str_starts_with($aliasName, $prefix) ? substr($aliasName, strlen($prefix)) : $aliasName;
    }

    private function target(string $aliasName, string $elementType, ?ClassDefinition $classDefinition = null): IndexTarget
    {
        return new IndexTarget($this->shortName($aliasName), $aliasName, $elementType, $classDefinition);
    }
}
