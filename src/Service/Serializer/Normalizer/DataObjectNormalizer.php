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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Normalizer;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DataObjectNormalizerException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Dependency\DependencyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowServiceInterface;
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
        private readonly WorkflowServiceInterface $workflowService,
        private readonly DependencyServiceInterface $dependencyService,
    ) {
    }

    /**
     * @param AbstractObject $object
     *
     * @throws Exception
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $skipLazyLoadedFields = SerializerContext::SKIP_LAZY_LOADED_FIELDS->containedInContext($context);

        $dataObject = $object;

        if ($dataObject instanceof Folder) {
            return $this->normalizeFolder($dataObject, $skipLazyLoadedFields);
        }

        if ($dataObject instanceof Concrete) {
            return $this->normalizeDataObject($dataObject, $skipLazyLoadedFields);
        }

        return [];
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof AbstractObject;
    }

    private function normalizeFolder(Folder $folder, bool $skipLazyLoadedFields): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($folder, $skipLazyLoadedFields),
            FieldCategory::STANDARD_FIELDS->value => [],
        ];
    }

    /**
     * @throws Exception
     */
    private function normalizeDataObject(Concrete $dataObject, bool $skipLazyLoadedFields): array
    {
        return [
            FieldCategory::SYSTEM_FIELDS->value => $this->normalizeSystemFields($dataObject, $skipLazyLoadedFields),
            FieldCategory::STANDARD_FIELDS->value => $this->normalizeStandardFields($dataObject),
        ];
    }

    /**
     * @throws Exception
     */
    private function normalizeSystemFields(AbstractObject $dataObject, bool $skipLazyLoadedFields): array
    {
        $pathLevels = $this->extractPathLevels($dataObject);

        $result = [
            SystemField::ID->value => $dataObject->getId(),
            SystemField::ELEMENT_TYPE->value => ElementType::DATA_OBJECT->value,
            SystemField::PARENT_ID->value => $dataObject->getParentId(),
            SystemField::CREATION_DATE->value => $this->formatTimestamp($dataObject->getCreationDate()),
            SystemField::MODIFICATION_DATE->value => $this->formatTimestamp($dataObject->getModificationDate()),
            SystemField::TYPE->value => $dataObject->getType(),
            SystemField::KEY->value => $dataObject->getKey(),
            SystemField::PATH->value => $dataObject->getPath(),
            SystemField::FULL_PATH->value => $dataObject->getRealFullPath(),
            SystemField::USER_OWNER->value => $dataObject->getUserOwner(),
            SystemField::USER_MODIFICATION->value => $dataObject->getUserModification(),
            SystemField::LOCKED->value => $dataObject->getLocked(),
            SystemField::IS_LOCKED->value => $dataObject->isLocked(),
        ];

        if ($dataObject instanceof Concrete) {
            $result = array_merge($result, [
                SystemField::CLASS_NAME->value => $dataObject->getClassName(),
                SystemField::CLASS_DEFINITION_ICON->value => $dataObject->getClass()->getIcon() ?: null,
                SystemField::PUBLISHED->value => $dataObject->getPublished(),
            ]);
        }

        if (!$skipLazyLoadedFields) {
            $result = array_merge($result, [
                SystemField::HAS_WORKFLOW_WITH_PERMISSIONS->value =>
                    $this->workflowService->hasWorkflowWithPermissions($dataObject),
                SystemField::DEPENDENCIES->value => $this->dependencyService->getRequiresDependencies($dataObject),
                SystemField::PATH_LEVELS->value => $pathLevels,
                SystemField::PATH_LEVEL->value => count($pathLevels),
                SystemField::TAGS->value => $this->extractTagIds($dataObject),
                SystemField::PARENT_TAGS->value => $this->extractParentTagIds($dataObject),
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

            if (isset($result['localizedfields'])) {
                $result = array_merge($result['localizedfields'], $result);
                unset($result['localizedfields']);
            }

            return $result;
        } catch (Exception $e) {
            throw new DataObjectNormalizerException($e->getMessage());
        }
    }
}
