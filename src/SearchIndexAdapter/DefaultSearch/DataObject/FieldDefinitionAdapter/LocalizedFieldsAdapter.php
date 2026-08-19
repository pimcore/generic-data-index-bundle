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
use Pimcore\Normalizer\NormalizerInterface;
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
        [$languages, $attributes] = $this->getLanguagesAndAttributes($value);
        if ($languages === []) {
            return null;
        }

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
                    ? $this->calculatedValueQueryStoreService->getLocalizedValue(
                        $dataObject,
                        $fieldDefinition,
                        $language
                    )
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
        [$languages, $attributes] = $this->getLanguagesAndAttributes($value);
        if ($languages === []) {
            return [];
        }
        $queryStore = $this->calculatedFieldsIndexModeResolver->getMode() === CalculatedFieldsIndexMode::QUERY_STORE;
        $result = [];
        foreach ($attributes as $attribute) {
            // In query_store mode calculated values come from the query table (handled in
            // normalize()); the inheritance pass must not call getLocalizedValue() for them,
            // which would execute the calculator and defeat the mode.
            if ($queryStore && $value->getFieldDefinition($attribute) instanceof CalculatedValue) {
                continue;
            }

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
        [$languages, $attributes] = $this->getLanguagesAndAttributes($value);
        if ($languages === []) {
            return [];
        }
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

    /**
     * The stored localized data is only needed for its language and attribute keys —
     * the values are resolved via getLocalizedValue(). Deriving the keys from the
     * internal data avoids running the full Localizedfields::normalize() pass over
     * every stored value (per element, once here and once in the inheritance pass).
     *
     * @return array{0: string[], 1: string[]} languages and attributes
     */
    private function getLanguagesAndAttributes(mixed $value): array
    {
        if (!$value instanceof Localizedfield) {
            return [[], []];
        }

        // getInternalData(true) loads lazy localized fields, as the previous full
        // normalize pass (via loadLazyData()) did
        $internalData = $value->getInternalData(true);
        if (empty($internalData)) {
            return [[], []];
        }

        /** @var Data\Localizedfields $fieldDefinition */
        $fieldDefinition = $this->getFieldDefinition();

        $attributes = [];
        foreach (array_keys(reset($internalData)) as $attribute) {
            // same inclusion rule as Localizedfields::normalize(): attributes whose
            // definition is gone (changed class definition) or not normalizable are skipped
            if ($fieldDefinition->getFieldDefinition($attribute) instanceof NormalizerInterface) {
                $attributes[] = $attribute;
            }
        }

        return [array_keys($internalData), $attributes];
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
