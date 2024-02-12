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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AssetSearchResultDenormalizer implements DenormalizerInterface
{
    /**
     * @param array $data
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): AssetSearchResultItem
    {
        $systemFields = $data[FieldCategory::SYSTEM_FIELDS->value];

        return new AssetSearchResultItem(
            id: $systemFields[SystemField::ID->value],
            parentId: $systemFields[SystemField::PARENT_ID->value],
            type: $systemFields[SystemField::TYPE->value],
            filename: $systemFields[SystemField::KEY->value],
            path: $systemFields[SystemField::PATH->value],
            fullPath: $systemFields[SystemField::FULL_PATH->value],
            mimeType: $systemFields[SystemField::MIME_TYPE->value],
            userOwner: $systemFields[SystemField::USER_OWNER->value],
            userModification: $systemFields[SystemField::USER_MODIFICATION->value],
            creationDate: strtotime($systemFields[SystemField::CREATION_DATE->value]),
            modificationDate: strtotime($systemFields[SystemField::MODIFICATION_DATE->value]),
            lock: $systemFields[SystemField::LOCKED->value],
            isLocked: $systemFields[SystemField::IS_LOCKED->value],
            // metaData: $this->denormalizeMetadata($data[FieldCategory::STANDARD_FIELDS->value]),
            children: false,
            searchIndexData: $data
        );
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_array($data) && is_subclass_of($type, AssetSearchResultItem::class);
    }

    private function denormalizeMetadata(array $standardFields): array
    {

    }
}
