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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class DataObjectNormalizer implements NormalizerInterface
{
    use ElementNormalizerTrait;

    public function __construct(
        private readonly FieldDefinitionServiceInterface $fieldDefinitionService,
    )
    {
    }

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

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof AbstractObject;
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
        $result = [
            SystemField::ID->value => $dataObject->getId(),
            SystemField::PARENT_ID->value => $dataObject->getParentId(),
            SystemField::CREATION_DATE->value => $this->formatTimestamp($dataObject->getCreationDate()),
            SystemField::MODIFICATION_DATE->value => $this->formatTimestamp($dataObject->getModificationDate()),
            SystemField::TYPE->value => $dataObject->getType(),
            SystemField::KEY->value => $dataObject->getKey(),
            SystemField::PATH->value => $dataObject->getPath(),
            SystemField::FULL_PATH->value => $dataObject->getRealFullPath(),
            SystemField::PATH_LEVELS->value => $this->extractPathLevels($dataObject),
            SystemField::TAGS->value => $this->extractTagIds($dataObject),
            SystemField::USER_OWNER->value => $dataObject->getUserOwner(),
        ];

        if ($dataObject instanceof Concrete) {
            $result = array_merge($result, [
                SystemField::CLASS_NAME->value => $dataObject->getClassName(),
                SystemField::PUBLISHED->value => $dataObject->getPublished(),
            ]);
        }

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

            $result = [];

            foreach ($dataObject->getClass()->getFieldDefinitions() as $key => $fieldDefinition) {

                $value = $dataObject->get($key);

                $value = $this->fieldDefinitionService->normalizeValue($fieldDefinition, $value);

                $result[$key] = $value;
            }

            AbstractObject::setGetInheritedValues($inheritedValuesBackup);
            Localizedfield::setGetFallbackValues($fallbackLanguagesBackup);

            return $result;
        } catch (Exception $e) {
            throw new DataObjectNormalizerException($e->getMessage());
        }
    }
}
