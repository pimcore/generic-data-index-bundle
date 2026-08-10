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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedValueQueryStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Localizedfield;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class LocalizedFieldsAdapter extends AbstractAdapter
{
    private LanguageServiceInterface $languageService;

    private CalculatedFieldsIndexModeResolverInterface $calculatedFieldsIndexModeResolver;

    private CalculatedValueQueryStoreServiceInterface $calculatedValueQueryStoreService;

    public function getIndexMapping(): array
    {
        $mapping = [
            'properties' => [],
        ];
        $languages = $this->languageService->getValidLanguages();
        /** @var Data\Localizedfields $fieldDefinition */
        $fieldDefinition = $this->getFieldDefinition();
        $childFieldDefinitions = $fieldDefinition->getFieldDefinitions();

        foreach ($languages as $language) {
            $languageProperties = [];

            foreach ($childFieldDefinitions as $childFieldDefinition) {
                $fieldDefinitionAdapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter(
                    $childFieldDefinition
                );
                if ($fieldDefinitionAdapter) {
                    $mappingKey = $fieldDefinitionAdapter->getIndexAttributeName();

                    $languageProperties[$mappingKey] = $fieldDefinitionAdapter->getIndexMapping();
                }
            }

            $mapping['properties'][$language] = [
                'properties' => $languageProperties,
            ];
        }

        return $mapping;
    }

    /**
     * @param mixed $value
     *
     * @return array|null
     *
     * @throws Exception
     */
    public function normalize(mixed $value): ?array
    {
        $indexData = $this->getIndexData($value);
        if (empty($indexData)) {
            return null;
        }

        $languages = array_keys($indexData);
        $attributes = array_keys(reset($indexData));
        $dataObject = $value->getObject();
        $result = [];
        foreach ($attributes as $attribute) {
            $fieldDefinition = $value->getFieldDefinition($attribute);
            // In query_store mode localized calculated values come from the localized query
            // table (save-time snapshot, per language); the calculator never runs here. This
            // is the dominant saving on many-language installations.
            $useQueryStore = $dataObject instanceof Concrete
                && $fieldDefinition instanceof CalculatedValue
                && $this->calculatedFieldsIndexModeResolver->getMode() === CalculatedFieldsIndexMode::QUERY_STORE;

            foreach ($languages as $language) {
                $localizedValue = $useQueryStore
                    ? $this->calculatedValueQueryStoreService->getLocalizedValue($dataObject, $fieldDefinition, $language)
                    : $value->getLocalizedValue($attribute, $language);
                $localizedValue =  $this->fieldDefinitionService->normalizeValue($fieldDefinition, $localizedValue);
                $result[$attribute][$language] = $localizedValue;
            }
        }

        return $result;
    }

    public function getInheritedData(
        Concrete $dataObject,
        int $objectId,
        mixed $value,
        string $key,
        ?string $language = null,
        ?callable $callback = null
    ): array {
        $indexData = $this->getIndexData($value);
        if (empty($indexData)) {
            return [];
        }
        $languages = array_keys($indexData);
        $attributes = array_keys(reset($indexData));
        $result = [];
        foreach ($attributes as $attribute) {
            foreach ($languages as $indexDataLanguage) {
                $data = $this->getInheritedDataForAdapter(
                    $dataObject,
                    $value,
                    $key,
                    $indexDataLanguage,
                    $attribute
                );

                foreach ($data as $itemKey => $item) {
                    $result[$itemKey] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getInheritedDataForBrick(
        Concrete $dataObject,
        Localizedfield $value,
        string $key,
        string $type
    ): array {
        $indexData = $this->getIndexData($value);
        if (empty($indexData)) {
            return [];
        }
        $languages = array_keys($indexData);
        $attributes = array_keys(reset($indexData));
        $result = [];
        $brickGetter = 'get' . ucfirst($type);
        foreach ($attributes as $attribute) {
            foreach ($languages as $indexDataLanguage) {
                $fieldGetter = 'get' . ucfirst($attribute);

                $data = $this->getInheritedDataForAdapter(
                    $dataObject,
                    $value,
                    $key,
                    $indexDataLanguage,
                    $attribute,
                    ['containerType' => 'objectbrick', 'containerKey' => $type],
                    static fn (
                        Concrete $parent, string $key, ?string $language
                    ) => $parent->get($key)->$brickGetter()?->$fieldGetter($language),
                );

                foreach ($data as $item) {
                    $result[$attribute . '.' . $indexDataLanguage] =
                        $item;
                }
            }
        }

        return $result;

    }

    private function getIndexData(mixed $value): ?array
    {
        if (!$value instanceof Localizedfield) {
            return [];
        }

        $value->loadLazyData();

        /** @var Data\Localizedfields $fieldDefinition */
        $fieldDefinition = $this->getFieldDefinition();

        return $fieldDefinition->normalize($value);
    }

    /**
     * @throws Exception
     */
    private function getInheritedDataForAdapter(
        concrete $dataObject,
        Localizedfield $value,
        string $key,
        string $language,
        string $attribute,
        array $context = [],
        ?callable $callback = null
    ): array {
        $adapter = $this->fieldDefinitionService->getFieldDefinitionAdapter(
            $value->getFieldDefinition($attribute, $context),
        );
        if (!$adapter) {
            return [];
        }
        $path = $attribute;
        if ($context !== [] && $context['containerType'] === 'objectbrick') {
            $path = $key;
        }

        return $adapter->getInheritedData(
            $dataObject,
            $dataObject->getId(),
            $value->getLocalizedValue($attribute, $language),
            $path,
            $language,
            $callback,
        );
    }

    #[Required]
    public function setLanguageService(LanguageServiceInterface $languageService): void
    {
        $this->languageService = $languageService;
    }

    #[Required]
    public function setCalculatedFieldsIndexModeResolver(
        CalculatedFieldsIndexModeResolverInterface $calculatedFieldsIndexModeResolver
    ): void {
        $this->calculatedFieldsIndexModeResolver = $calculatedFieldsIndexModeResolver;
    }

    #[Required]
    public function setCalculatedValueQueryStoreService(
        CalculatedValueQueryStoreServiceInterface $calculatedValueQueryStoreService
    ): void {
        $this->calculatedValueQueryStoreService = $calculatedValueQueryStoreService;
    }
}
