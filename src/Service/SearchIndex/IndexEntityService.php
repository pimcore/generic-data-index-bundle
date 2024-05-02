<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;

/**
 * @internal
 */
final readonly class IndexEntityService implements IndexEntityServiceInterface
{
    public function __construct(
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private ElementServiceInterface $elementService,
    ) {
    }

    public function getByEntityName(string $entityName): IndexEntity
    {
        return new IndexEntity(
            $entityName,
            $this->searchIndexConfigService->getIndexName($entityName),
            $this->getIndexType($entityName)
        );
    }

    public function getByIndexName(string $indexName): IndexEntity
    {
        return $this->getByEntityName(
            str_replace($this->searchIndexConfigService->getIndexPrefix(), '', $indexName)
        );
    }

    private function getIndexType(string $entityName): ?IndexType
    {
        $entityName = strtolower($entityName);

        $indexType = null;
        if (IndexName::ASSET->value === $entityName) {
            $indexType = IndexType::ASSET;
        } elseif (IndexName::DOCUMENT->value === $entityName) {
            $indexType = IndexType::DOCUMENT;
        } elseif (IndexName::DATA_OBJECT->value === $entityName) {
            $indexType = IndexType::DATA_OBJECT;
        } elseif ($this->elementService->classDefinitionExists($entityName)) {
            $indexType = IndexType::DATA_OBJECT;
        }

        return $indexType;
    }
}
