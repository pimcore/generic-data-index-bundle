<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionService;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\Attribute\Required;

class DataObjectIndexService extends AbstractIndexService
{
    protected FieldDefinitionService $fieldDefinitionService;

    #[Required]
    public function setFieldDefinitionService(FieldDefinitionService $fieldDefinitionService): void
    {
        $this->fieldDefinitionService = $fieldDefinitionService;
    }

    /**
     * @param Concrete $element
     */
    protected function getIndexName(ElementInterface $element): string
    {
        $classDefinitionName = $element->getClassName();
        return $this->searchIndexConfigService->getIndexName($classDefinitionName);
    }

    /**
     * @param ClassDefinition $classDefinition
     *
     * @return string
     */
    protected function getCurrentFullIndexName(ClassDefinition $classDefinition): string
    {
        $indexName = $this->searchIndexConfigService->getIndexName($classDefinition->getName());
        $currentIndexVersion = $this->openSearchService->getCurrentIndexVersion($indexName);

        return $indexName . '-' . ($currentIndexVersion === 'even' ? 'even' : 'odd');
    }


    public function createIndex(ClassDefinition $classDefinition): DataObjectIndexService
    {
        $fullIndexName = $this->getCurrentFullIndexName($classDefinition);
        $this
            ->openSearchService
            ->createIndex($fullIndexName)
            ->addAlias($fullIndexName, $this->searchIndexConfigService->getIndexName($classDefinition->getName()))
        ;

        return $this;
    }


    public function deleteIndex(ClassDefinition $classDefinition): DataObjectIndexService
    {
        $this->openSearchService
            ->deleteIndex($this->getCurrentFullIndexName($classDefinition))
            ->deleteIndex($this->searchIndexConfigService->getIndexName($classDefinition->getName()));

        return $this;
    }


    public function extractMapping(ClassDefinition $classDefinition)
    {
        $mappingProperties = $this->extractSystemFieldsMapping();

        foreach ($classDefinition->getFieldDefinitions() as $fieldDefinition) {
            if (!$fieldDefinition->getName()) {
                continue;
            }
            $fieldDefinitionAdapter = $this->fieldDefinitionService->getFieldDefinitionAdapter($fieldDefinition);
            if ($fieldDefinitionAdapter) {

                //localizedfields are nested with other ESMappings
                if ($fieldDefinition instanceof ClassDefinition\Data\Localizedfields) {
                    foreach ($fieldDefinitionAdapter->getOpenSearchMapping() as $mappingKey => $mappingEntry) {
                        $mappingProperties[FieldCategory::STANDARD_FIELDS->value]['properties'][$mappingKey] = $mappingEntry;
                    }
                } else {
                    list($mappingKey, $mappingEntry) = $fieldDefinitionAdapter->getOpenSearchMapping();
                    $mappingProperties[FieldCategory::STANDARD_FIELDS->value]['properties'][$mappingKey] = $mappingEntry;
                }
            }
        }

        $mappingProperties[FieldCategory::CUSTOM_FIELDS->value] = [];

        #$extractMappingEvent = new ExtractMappingEvent($classDefinition, $mappingProperties[FieldCategory::CUSTOM_FIELDS->value]);
        #$this->eventDispatcher->dispatch($extractMappingEvent);
        #$mappingProperties[FieldCategory::CUSTOM_FIELDS->value]['properties'] = $extractMappingEvent->getCustomFieldsMapping();

        $mappingParams = [
            'index' => $this->searchIndexConfigService->getIndexName($classDefinition->getName()),
            'body' => [
                '_source' => [
                    'enabled' => true
                ],
                'properties' => $mappingProperties
            ]
        ];

        return $mappingParams;
    }

    /**
     * updates mapping for given Object class
     *  - update mapping without recreating index
     *  - if that fails, create new index and reindex on ES side
     *  - if that also fails, throws exception
     *
     * @param ClassDefinition $classDefinition
     * @param bool $forceCreateIndex
     *
     * @return $this
     */
    public function updateMapping(ClassDefinition $classDefinition, $forceCreateIndex = false)
    {
        $index = $this->searchIndexConfigService->getIndexName($classDefinition->getName());

        if ($forceCreateIndex || !$this->openSearchClient->indices()->existsAlias(['name' => $index])) {
            $this->createIndex($classDefinition);
        }

        //updating mapping without recreating index
        try {
            $this->doUpdateMapping($classDefinition);
        } catch (\Exception $e) {
            $this->logger->info($e);
            //try recreating index
            $this->openSearchService->reindex($index, $this->extractMapping($classDefinition));
        }

        return $this;
    }

    /**
     * updates mapping for index - throws exception if not successful
     *
     * @param ClassDefinition $classDefinition
     *
     * @return $this
     */
    protected function doUpdateMapping(ClassDefinition $classDefinition): DataObjectIndexService
    {
        $mapping = $this->extractMapping($classDefinition);
        $response = $this->openSearchClient->indices()->putMapping($mapping);
        $this->logger->debug(json_encode($response));

        return $this;
    }

    /**
     * @param Concrete $element
     */
    protected function getIndexData(ElementInterface $element): array
    {

        $dataObject = $element;

        $systemFields = $this->getSystemFieldsIndexData($dataObject);
        $standardFields = [];
        $customFields = [];

        foreach ($dataObject->getClass()->getFieldDefinitions() as $key => $fieldDefinition) {

            $value = $dataObject->get($key);
            if($fieldDefinition instanceof NormalizerInterface) {
                $value = $fieldDefinition->normalize($value);
            }

            $standardFields[$key] = $value;
        }

        //dispatch event before building checksum
        #$updateIndexDataEvent = new UpdateIndexDataEvent($dataObject, $customFields);
        #$this->eventDispatcher->dispatch($updateIndexDataEvent);
        #$customFields = $updateIndexDataEvent->getCustomFields();

        $checksum = crc32(json_encode([$systemFields, $standardFields, $customFields]));
        $systemFields[FieldCategory\SystemField::CHECKSUM->value] = $checksum;

        return [
            FieldCategory::SYSTEM_FIELDS->value => $systemFields,
            FieldCategory::STANDARD_FIELDS->value => $standardFields,
            FieldCategory::CUSTOM_FIELDS->value => $customFields
        ];
    }

    /**
     * @param ClassDefinition $classDefinition
     * @param string $aliasName
     *
     * @return $this
     */
    public function addClassDefinitionToAlias(ClassDefinition $classDefinition, string $aliasName)
    {
        if (!$this->existsAliasForClassDefinition($classDefinition, $aliasName)) {
            $response = $this->openSearchClient->indices()->putAlias([
                'name' => $this->prefixAliasName($aliasName),
                'index' => $this->getCurrentFullIndexName($classDefinition)
            ]);
            $this->logger->debug(json_encode($response));
        }

        return $this;
    }

    /**
     * @param ClassDefinition $classDefinition
     * @param string $aliasName
     *
     * @return $this
     */
    public function removeClassDefinitionFromAlias(ClassDefinition $classDefinition, string $aliasName)
    {
        if ($this->existsAliasForClassDefinition($classDefinition, $aliasName)) {
            $response = $this->openSearchClient->indices()->deleteAlias([
                'name' => $this->prefixAliasName($aliasName),
                'index' => $this->getCurrentFullIndexName($classDefinition)
            ]);
            $this->logger->debug(json_encode($response));
        }

        return $this;
    }

    /**
     * @param ClassDefinition $classDefinition
     * @param string $aliasName
     *
     * @return bool
     */
    protected function existsAliasForClassDefinition(ClassDefinition $classDefinition, string $aliasName)
    {
        return $this->openSearchClient->indices()->existsAlias([
            'name' => $this->prefixAliasName($aliasName),
            'index' => $this->getCurrentFullIndexName($classDefinition)
        ]);
    }

    private function prefixAliasName(string $aliasName): string
    {
        return $this->searchIndexConfigService->prefixIndexName($aliasName);
    }

    /**
     * returns core fields index data array for given data object
     */
    protected function getSystemFieldsIndexData(Concrete $dataObject): array
    {
        $date = new \DateTime();

        return [
            FieldCategory\SystemField::ID->value => $dataObject->getId(),
            FieldCategory\SystemField::CREATION_DATE->value => $date->setTimestamp($dataObject->getCreationDate())->format(\DateTimeInterface::ATOM),
            FieldCategory\SystemField::MODIFICATION_DATE->value => $date->setTimestamp($dataObject->getModificationDate())->format(\DateTimeInterface::ATOM),
            FieldCategory\SystemField::PUBLISHED->value => $dataObject->getPublished(),
            FieldCategory\SystemField::TYPE->value => $dataObject->getType(),
            FieldCategory\SystemField::KEY->value => $dataObject->getKey(),
            FieldCategory\SystemField::PATH->value => $dataObject->getPath(),
            FieldCategory\SystemField::FULL_PATH->value => $dataObject->getRealFullPath(),
            FieldCategory\SystemField::PATH_LEVELS->value => $this->extractPathLevels($dataObject),
            FieldCategory\SystemField::TAGS->value => $this->extractTagIds($dataObject),
            FieldCategory\SystemField::CLASS_NAME->value => $dataObject->getClassName(),
            #FieldCategory\SystemField::NAME => $this->nameExtractorService->extractAllLanguageNames($dataObject),
            #FieldCategory\SystemField::THUMBNAIL => $this->mainImageExtractorService->extractThumbnail($dataObject),
            #FieldCategory\SystemField::COLLECTIONS => $this->getCollectionIdsByElement($dataObject),
            #FieldCategory\SystemField::PUBLIC_SHARES => $this->getPublicShareIdsByElement($dataObject),
            FieldCategory\SystemField::USER_OWNER->value => $dataObject->getUserOwner()
        ];
    }

    /**
     * Called in index.yml
     *
     * @param array $coreFieldsConfig
     */
    public function setCoreFieldsConfig(array $coreFieldsConfig)
    {
        if (is_array($coreFieldsConfig['general']) && is_array($coreFieldsConfig['data_object'])) {
            $this->coreFieldsConfig = array_merge($coreFieldsConfig['general'], $coreFieldsConfig['data_object']);
        }
    }
}
