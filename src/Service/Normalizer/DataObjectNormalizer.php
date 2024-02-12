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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Normalizer;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DataObjectNormalizerException;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\ElementNormalizerTrait;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Localizedfield;

/**
 * @internal
 */
final class DataObjectNormalizer extends \Pimcore\Serializer\Normalizer\DataObjectNormalizer
{
    use ElementNormalizerTrait;

    /**
     * @param AbstractObject $object
     *
     * @throws Exception
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $dataObject = $object;

        if ($dataObject instanceof Folder) {
            return $this->normalizeFolder($dataObject);
        }

        if ($dataObject instanceof Concrete) {
            return $this->normalizeDataObject($dataObject);
        }

        return [];
    }

    private function normalizeFolder(Folder $folder): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($folder),
            FieldCategory::STANDARD_FIELDS->value => [],
        ];
    }

    /**
     * @throws Exception
     */
    private function normalizeDataObject(Concrete $dataObject): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($dataObject),
            FieldCategory::STANDARD_FIELDS->value => $this->normalizeStandardFields($dataObject),
        ];
    }

    private function normalizeSystemFields(AbstractObject $dataObject): array
    {
        $generalAttributes = $this->normalizeGeneralAttributes($dataObject);

        $result = [];

        foreach ([
            SystemField::ID->value,
            SystemField::PARENT_ID->value,
            SystemField::CREATION_DATE->value,
            SystemField::MODIFICATION_DATE->value,
            SystemField::TYPE->value,
            SystemField::KEY->value,
            SystemField::PATH->value,
            SystemField::FULL_PATH->value,
            SystemField::USER_OWNER->value,
            SystemField::CLASS_NAME->value,
            SystemField::PUBLISHED->value,
        ] as $field) {
            if (isset($generalAttributes[$field])) {
                $result[$field] = $generalAttributes[$field];
            }
        }

        $result[SystemField::PATH_LEVELS->value] = $this->extractPathLevels($dataObject);
        $result[SystemField::TAGS->value] = $this->extractTagIds($dataObject);

        return $result;
    }

    /**
     * @throws DataObjectNormalizerException
     */
    private function normalizeStandardFields(Concrete $dataObject): array
    {
        try {
            $inheritedValuesBackup = AbstractObject::doGetInheritedValues();
            $fallbackLanguagesBackup = Localizedfield::doGetFallbackValues();
            AbstractObject::setGetInheritedValues(true);
            Localizedfield::setGetFallbackValues(true);

            $result = $this->normalizeFieldDefinitions($dataObject);

            AbstractObject::setGetInheritedValues($inheritedValuesBackup);
            Localizedfield::setGetFallbackValues($fallbackLanguagesBackup);

            return $result;
        } catch (Exception $e) {
            throw new DataObjectNormalizerException($e->getMessage());
        }
    }
}
