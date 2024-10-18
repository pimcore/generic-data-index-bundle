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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Localizedfield;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class LocalizedFieldsAdapter extends AbstractAdapter
{
    private LanguageServiceInterface $languageService;

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
        $result = [];
        foreach ($attributes as $attribute) {
            foreach ($languages as $language) {
                $localizedValue = $value->getLocalizedValue($attribute, $language);
                $fieldDefinition = $value->getFieldDefinition($attribute);
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
        callable $callback = null
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
}
