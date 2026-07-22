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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\SearchResultItem\InheritedData;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DataObjectTypeSerializationHandlerService;
use Pimcore\Model\DataObject\AbstractObject;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final readonly class DataObjectSearchResultDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private DataObjectTypeSerializationHandlerService $typeHandlerService
    ) {
    }

    /**
     * @param array $data
     */
    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): DataObjectSearchResultItem {

        $serializationHandler = $this->typeHandlerService->getSerializationHandler(
            SystemField::TYPE->getData($data)
        );

        if ($serializationHandler) {
            $searchResultItem = $serializationHandler->createSearchResultModel($data);
        } else {
            $searchResultItem = new DataObjectSearchResultItem();
        }

        $published = SystemField::TYPE->getData($data) === 'folder' || SystemField::PUBLISHED->getData($data);

        $searchResultItem
            ->setId(SystemField::ID->getData($data))
            ->setClassId(SystemField::CLASS_ID->getData($data) ?? '')
            ->setClassName(SystemField::CLASS_NAME->getData($data) ?? '')
            ->setClassDefinitionIcon(SystemField::CLASS_DEFINITION_ICON->getData($data))
            ->setParentId(SystemField::PARENT_ID->getData($data))
            ->setType(SystemField::TYPE->getData($data))
            ->setPublished($published)
            ->setKey(SystemField::KEY->getData($data))
            ->setIndex(SystemField::INDEX->getData($data))
            ->setChildrenSortBy(
                SystemField::CHILDREN_SORT_BY->getData(
                    $data
                ) ?? AbstractObject::OBJECT_CHILDREN_SORT_BY_DEFAULT)
            ->setChildrenSortOrder(
                SystemField::CHILDREN_SORT_ORDER->getData(
                    $data
                ) ?? AbstractObject::OBJECT_CHILDREN_SORT_ORDER_DEFAULT)
            ->setPath(SystemField::PATH->getData($data))
            ->setFullPath(SystemField::FULL_PATH->getData($data))
            ->setUserOwner(SystemField::USER_OWNER->getData($data) ?? 0)
            ->setUserModification(SystemField::USER_MODIFICATION->getData($data))
            ->setLocked(SystemField::LOCKED->getData($data))
            ->setCreationDate(strtotime(SystemField::CREATION_DATE->getData($data)))
            ->setModificationDate(strtotime(SystemField::MODIFICATION_DATE->getData($data)));

        if (SerializerContext::SKIP_LAZY_LOADED_FIELDS->containedInContext($context)) {
            return $searchResultItem;
        }

        if (isset($data[FieldCategory::STANDARD_FIELDS->value][FieldCategory::INHERITED_FIELDS->value])) {
            $searchResultItem->setInheritedFields(
                $this->hydrateInheritedData(
                    $data[FieldCategory::STANDARD_FIELDS->value][FieldCategory::INHERITED_FIELDS->value]
                )
            );
        }

        return $searchResultItem
            ->setHasWorkflowWithPermissions(SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->getData($data))
            ->setHasChildren(SystemField::HAS_CHILDREN->getData($data))
            ->setSearchIndexData($data);

    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return is_array($data) && is_subclass_of($type, DataObjectSearchResultItem::class);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
    }

    private function hydrateInheritedData(array $inheritedData): array
    {
        $result = [];

        foreach ($inheritedData as $key => $inheritedEntry) {
            $result[] = new InheritedData(
                $key,
                $inheritedEntry['originId']
            );
        }

        return $result;
    }
}
