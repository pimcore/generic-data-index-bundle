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

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageService;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Contracts\Service\Attribute\Required;

class LocalizedFieldsAdapter extends DefaultAdapter
{
    protected LocaleServiceInterface $localeService;

    /** @var Data\Localizedfields */
    protected Data $fieldDefinition;

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
        /** @var array $mapping */
        $mapping = [];
        /** @var string[] $languages */
        $languages = $this->languageService->getValidLanguages();
        /** @var Data[] $childFieldDefinitions */
        $childFieldDefinitions = $this->fieldDefinition->getFieldDefinitions();

        foreach ($childFieldDefinitions as $childFieldDefinition) {
            $fieldDefinitionAdapter = $this->fieldDefinitionService->getFieldDefinitionAdapter($childFieldDefinition);
            if ($fieldDefinitionAdapter) {

                list($mappingKey, $mappingStructure) = $fieldDefinitionAdapter->getOpenSearchMapping();

                $mapping[$mappingKey] = [
                    'properties' => [],
                ];

                foreach ($languages as $language) {
                    $mapping[$mappingKey]['properties'][$language] = $mappingStructure;
                }
            }
        }

        return $mapping;
    }

    public function getIndexData(Concrete $object): mixed
    {
        /** @var array $indexData */
        $indexData = [];
        /** @var string $localeBackup */
        $localeBackup = $this->localeService->getLocale();
        /** @var string[] $validLanguages */
        $validLanguages = $this->languageService->getValidLanguages();

        if ($validLanguages) {
            foreach ($validLanguages as $language) {
                /** @var Data $fieldDefinition */
                foreach ($this->fieldDefinition->getFieldDefinitions() as $key => $fieldDefinition) {
                    $this->localeService->setLocale($language);

                    $fieldDefinitionAdapter = $this->fieldDefinitionService->getFieldDefinitionAdapter($fieldDefinition);
                    if ($fieldDefinitionAdapter) {
                        $indexData[$key][$language] = $fieldDefinitionAdapter->getIndexData($object);
                    }
                }
            }
        }

        $this->localeService->setLocale($localeBackup);

        return $indexData;
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
}
