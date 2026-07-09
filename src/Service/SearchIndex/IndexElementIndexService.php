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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Doctrine\DBAL\Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Db\DbResolverInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;

/**
 * @internal
 */
final readonly class IndexElementIndexService implements IndexElementIndexServiceInterface
{
    private const DATA_OBJECT_TABLE = 'objects';

    private const DOCUMENT_TABLE = 'documents';

    public function __construct(
        private BulkOperationServiceInterface $bulkOperationService,
        private DbResolverInterface $dbResolver,
        private DataObjectSearchServiceInterface $dataObjectSearchService,
        private DocumentSearchServiceInterface $documentSearchService,
        private SearchIndexConfigServiceInterface $searchIndexConfigService
    ) {
    }

    /**
     * @throws Exception
     */
    public function updateSiblings(AbstractObject|Document $element, string $elementType): void
    {
        $newIndex = $element->getIndex();
        if ($newIndex === $this->getIndexedIndex($element)
        ) {
            return;
        }

        if ($elementType === ElementType::DATA_OBJECT->value &&
            $element->getParent()?->getChildrenSortBy() !== AbstractObject::OBJECT_CHILDREN_SORT_BY_INDEX
        ) {
            return;
        }

        $index = 0;
        foreach ($this->getSiblings($element) as $sibling) {
            if ($index === $newIndex) {
                $index++;
            }

            $this->bulkOperationService->addUpdate(
                $this->getIndexName($sibling, $elementType),
                $sibling['id'],
                [FieldCategory::SYSTEM_FIELDS->value => ['index' => $index]],
            );

            $index++;
        }

        $this->bulkOperationService->commit();
    }

    /**
     * @throws Exception
     */
    public function resetChildrenIndexBy(AbstractObject $element): void
    {
        if (!$element->hasChildren() || $this->getIndexedSortBy($element) === $element->getChildrenSortBy()) {
            return;
        }

        $index = 0;
        foreach ($this->getDataObjectChildren($element) as $child) {
            $this->bulkOperationService->addUpdate(
                $this->getIndexName($child, ElementType::DATA_OBJECT->value),
                $child['id'],
                [FieldCategory::SYSTEM_FIELDS->value => ['index' => $index]],
            );

            $index++;
        }

        $this->bulkOperationService->commit();
    }

    private function getIndexedIndex(AbstractObject|Document $element): ?int
    {
        if ($element instanceof Document) {
            return $this->documentSearchService->byId($element->getId())?->getIndex();
        }

        return $this->dataObjectSearchService->byId($element->getId())?->getIndex();
    }

    private function getIndexedSortBy(AbstractObject $element): ?string
    {
        return $this->dataObjectSearchService->byId($element->getId())?->getChildrenSortBy();
    }

    /**
     * @throws Exception
     */
    private function getSiblings(AbstractObject|Document $element): array
    {
        $query = $element instanceof Document ? $this->getDocumentSiblingsQuery() : $this->getDataObjectSiblingsQuery();

        return $this->dbResolver->get()->fetchAllAssociative($query, [$element->getParentId(), $element->getId()]);
    }

    private function getDocumentSiblingsQuery(): string
    {
        return 'SELECT id, `type` FROM ' . self::DOCUMENT_TABLE
            . ' WHERE parentId = ? AND id != ? ORDER BY `index` ASC';
    }

    private function getDataObjectSiblingsQuery(): string
    {
        return 'SELECT id, `type`, `className` FROM ' . self::DATA_OBJECT_TABLE
            . ' WHERE parentId = ? AND id != ? ORDER BY `index` ASC';
    }

    /**
     * @throws Exception
     */
    private function getDataObjectChildren(AbstractObject $parent): array
    {
        return $this->dbResolver->get()->fetchAllAssociative(
            'SELECT id, `modificationDate`, `type`, `className` FROM ' . self::DATA_OBJECT_TABLE
            . ' WHERE parentId = ? ORDER BY `key` ' . $parent->getChildrenSortOrder(),
            [$parent->getId()]
        );
    }

    private function getIndexName(array $siblingData, string $elementType): string
    {
        if ($elementType === ElementType::DOCUMENT->value) {
            return $this->searchIndexConfigService->getIndexName(IndexName::DOCUMENT->value);
        }

        $alias = $siblingData['type'] === 'folder' ? IndexName::DATA_OBJECT_FOLDER->value : $siblingData['className'];

        return $this->searchIndexConfigService->getIndexName($alias, $siblingData['type'] !== 'folder');
    }
}
