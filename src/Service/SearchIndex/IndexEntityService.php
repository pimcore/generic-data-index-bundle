<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
        $isClass = false;
        $indexType = $this->getIndexType($entityName);

        if ($indexType === IndexType::DATA_OBJECT) {
            $isClass = $this->elementService->classDefinitionExists($entityName);
        }

        return new IndexEntity(
            $entityName,
            $this->searchIndexConfigService->getIndexName($entityName, $isClass),
            $indexType
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
