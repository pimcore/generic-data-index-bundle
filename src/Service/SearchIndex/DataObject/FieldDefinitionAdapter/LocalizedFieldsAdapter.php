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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageService;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Contracts\Service\Attribute\Required;

class LocalizedFieldsAdapter extends AbstractAdapter
{
    protected LocaleServiceInterface $localeService;

    protected FieldDefinitionService $fieldDefinitionService;

    protected LanguageService $languageService;

    /**
     * @param LocaleServiceInterface $localeService
     *
     * @required
     *
     * @throws \Exception
     */
    public function setLocaleService(LocaleServiceInterface $localeService): void
    {
        $this->localeService = $localeService;
    }

    public function getOpenSearchMapping(): array
    {
        $mapping = [
            'properties' => [],
        ];
        $languages = $this->languageService->getValidLanguages();
        $childFieldDefinitions = $this->getFieldDefinition()->getFieldDefinitions();

        foreach ($languages as $language) {
            $languageProperties = [];

            foreach ($childFieldDefinitions as $childFieldDefinition) {
                $fieldDefinitionAdapter = $this->fieldDefinitionService->getFieldDefinitionAdapter(
                    $childFieldDefinition
                );
                if ($fieldDefinitionAdapter) {
                    $mappingKey = $fieldDefinitionAdapter->getOpenSearchAttributeName();

                    $languageProperties[$mappingKey] = $fieldDefinitionAdapter->getOpenSearchMapping();
                }
            }

            $mapping['properties'][$language] = [
                'properties' => $languageProperties,
            ];
        }

        return $mapping;
    }

    #[Required]
    public function setFieldDefinitionService(FieldDefinitionService $fieldDefinitionService): void
    {
        $this->fieldDefinitionService = $fieldDefinitionService;
    }

    #[Required]
    public function setLanguageService(LanguageService $languageService): void
    {
        $this->languageService = $languageService;
    }

    public function getFieldDefinition(): Data\Localizedfields
    {
        if ($this->fieldDefinition instanceof Data\Localizedfields) {
            return $this->fieldDefinition;
        }

        throw new InvalidArgumentException(
            sprintf(
                'FieldDefinition must be of type %s, %s given',
                Data\Localizedfields::class,
                get_class($this->fieldDefinition)
            )
        );
    }
}
