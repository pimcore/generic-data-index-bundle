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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetMetaData;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer\AssetNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AssetSearchResultDenormalizer implements DenormalizerInterface
{
    /**
     * @param array $data
     */
    public function denormalize(
        mixed $data,
        string $type,
        string $format = null,
        array $context = []
    ): AssetSearchResultItem {
        return new AssetSearchResultItem(
            id: SystemField::ID->getData($data),
            parentId: SystemField::PARENT_ID->getData($data),
            type: SystemField::TYPE->getData($data),
            key: SystemField::KEY->getData($data),
            path: SystemField::PATH->getData($data),
            fullPath: SystemField::FULL_PATH->getData($data),
            mimeType: SystemField::MIME_TYPE->getData($data),
            userOwner: SystemField::USER_OWNER->getData($data),
            userModification: SystemField::USER_MODIFICATION->getData($data),
            locked: SystemField::LOCKED->getData($data),
            isLocked: SystemField::IS_LOCKED->getData($data),
            metaData: $this->hydrateMetadata($data[FieldCategory::STANDARD_FIELDS->value]),
            creationDate: strtotime(SystemField::CREATION_DATE->getData($data)),
            modificationDate: strtotime(SystemField::MODIFICATION_DATE->getData($data)),
            hasWorkflowWithPermissions: SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->getData($data),
            hasChildren: SystemField::HAS_CHILDREN->getData($data),
            searchIndexData: $data
        );
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_array($data) && is_subclass_of($type, AssetSearchResultItem::class);
    }

    /** @var AssetMetaData[] $standardFields */
    private function hydrateMetadata(array $standardFields): array
    {
        $result = [];

        foreach($standardFields as $language => $fields) {
            foreach($fields as $fieldName => $fieldData) {
                $result[] = new AssetMetaData(
                    name: $fieldName,
                    language: $language !== AssetNormalizer::NOT_LOCALIZED_KEY ? $language : null,
                    type: $fieldData['type'],
                    data: $fieldData['data'],
                );
            }
        }

        return $result;
    }
}
