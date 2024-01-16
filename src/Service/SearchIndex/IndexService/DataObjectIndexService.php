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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use DateTimeInterface;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
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

    public function extractMapping(ClassDefinition $classDefinition): array
    {
        $mappingProperties = [
            FieldCategory::SYSTEM_FIELDS->value => [
                'properties' => $this->searchIndexConfigService
                    ->getSystemFieldsSettings(SearchIndexConfigService::SYSTEM_FIELDS_SETTINGS_DATA_OBJECT),
            ],
            FieldCategory::STANDARD_FIELDS->value => [
                'properties' => []
            ],
            FieldCategory::CUSTOM_FIELDS->value => [],
        ];

        // $standardFeildProperties =
        foreach ($classDefinition->getFieldDefinitions() as $fieldDefinition) {
            if (!$fieldDefinition->getName()) {
                continue;
            }
            $fieldDefinitionAdapter = $this->fieldDefinitionService->getFieldDefinitionAdapter($fieldDefinition);
            if ($fieldDefinitionAdapter) {
                $mappingProperties[FieldCategory::STANDARD_FIELDS->value]['properties'][$fieldDefinitionAdapter->getOpenSearchAttributeName()] = $fieldDefinitionAdapter->getOpenSearchMapping();
            }
        }


        //$extractMappingEvent = new ExtractMappingEvent($classDefinition, $mappingProperties[FieldCategory::CUSTOM_FIELDS->value]);
        //$this->eventDispatcher->dispatch($extractMappingEvent);
        //$mappingProperties[FieldCategory::CUSTOM_FIELDS->value]['properties'] = $extractMappingEvent->getCustomFieldsMapping();

        $mappingParams = [
            'index' => $this->searchIndexConfigService->getIndexName($classDefinition->getName()),
            'body' => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $mappingProperties,
            ],
        ];

        return $mappingParams;
    }

    /**
     * updates mapping for given Object class
     *  - update mapping without recreating index
     *  - if that fails, create new index and reindex on ES side
     *  - if that also fails, throws exception
     *
     * @throws Exception
     */
    public function updateMapping(ClassDefinition $classDefinition, bool $forceCreateIndex = false): DataObjectIndexService
    {
        $index = $this->searchIndexConfigService->getIndexName($classDefinition->getName());

        if ($forceCreateIndex || !$this->openSearchClient->indices()->existsAlias(['name' => $index])) {
            $this->createIndex($classDefinition);
        }

        //updating mapping without recreating index
        try {
            $this->doUpdateMapping($classDefinition);
        } catch (Exception $e) {
            $this->logger->info($e);
            //try recreating index
            $this->openSearchService->reindex($index, $this->extractMapping($classDefinition));
        }

        return $this;
    }

    /**
     * updates mapping for index - throws exception if not successful
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
     *
     * @throws Exception
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
        //$updateIndexDataEvent = new UpdateIndexDataEvent($dataObject, $customFields);
        //$this->eventDispatcher->dispatch($updateIndexDataEvent);
        //$customFields = $updateIndexDataEvent->getCustomFields();

        $checksum = crc32(json_encode([$systemFields, $standardFields, $customFields]));
        $systemFields[SystemField::CHECKSUM->value] = $checksum;

        return [
            FieldCategory::SYSTEM_FIELDS->value => $systemFields,
            FieldCategory::STANDARD_FIELDS->value => $standardFields,
            FieldCategory::CUSTOM_FIELDS->value => $customFields,
        ];
    }

    public function addClassDefinitionToAlias(ClassDefinition $classDefinition, string $aliasName): DataObjectIndexService
    {
        if (!$this->existsAliasForClassDefinition($classDefinition, $aliasName)) {
            $response = $this->openSearchClient->indices()->putAlias([
                'name' => $this->prefixAliasName($aliasName),
                'index' => $this->getCurrentFullIndexName($classDefinition),
            ]);
            $this->logger->debug(json_encode($response));
        }

        return $this;
    }

    public function removeClassDefinitionFromAlias(ClassDefinition $classDefinition, string $aliasName): DataObjectIndexService
    {
        if ($this->existsAliasForClassDefinition($classDefinition, $aliasName)) {
            $response = $this->openSearchClient->indices()->deleteAlias([
                'name' => $this->prefixAliasName($aliasName),
                'index' => $this->getCurrentFullIndexName($classDefinition),
            ]);
            $this->logger->debug(json_encode($response));
        }

        return $this;
    }

    protected function existsAliasForClassDefinition(ClassDefinition $classDefinition, string $aliasName): bool
    {
        return $this->openSearchClient->indices()->existsAlias([
            'name' => $this->prefixAliasName($aliasName),
            'index' => $this->getCurrentFullIndexName($classDefinition),
        ]);
    }

    private function prefixAliasName(string $aliasName): string
    {
        return $this->searchIndexConfigService->prefixIndexName($aliasName);
    }

    protected function getSystemFieldsIndexData(Concrete $dataObject): array
    {
        $date = new \DateTime();

        return [
            SystemField::ID->value => $dataObject->getId(),
            SystemField::CREATION_DATE->value => $date->setTimestamp($dataObject->getCreationDate())->format(DateTimeInterface::ATOM),
            SystemField::MODIFICATION_DATE->value => $date->setTimestamp($dataObject->getModificationDate())->format(DateTimeInterface::ATOM),
            SystemField::PUBLISHED->value => $dataObject->getPublished(),
            SystemField::TYPE->value => $dataObject->getType(),
            SystemField::KEY->value => $dataObject->getKey(),
            SystemField::PATH->value => $dataObject->getPath(),
            SystemField::FULL_PATH->value => $dataObject->getRealFullPath(),
            SystemField::PATH_LEVELS->value => $this->extractPathLevels($dataObject),
            SystemField::TAGS->value => $this->extractTagIds($dataObject),
            SystemField::CLASS_NAME->value => $dataObject->getClassName(),
            //SystemField::NAME => $this->nameExtractorService->extractAllLanguageNames($dataObject),
            //SystemField::THUMBNAIL => $this->mainImageExtractorService->extractThumbnail($dataObject),
            //SystemField::COLLECTIONS => $this->getCollectionIdsByElement($dataObject),
            //SystemField::PUBLIC_SHARES => $this->getPublicShareIdsByElement($dataObject),
            SystemField::USER_OWNER->value => $dataObject->getUserOwner(),
        ];
    }

    /**
     * @throws Exception
     */
    public function getRelatedItemsOnUpdateQuery(ElementInterface $element, string $operation, int $operationTime, bool $includeElement = false): ?QueryBuilder
    {
        if(!$element instanceof Concrete) {
            return null;
        }

        if(!$element->getClass()->getAllowInherit()) {
            return null;
        }

        $select = $this->dbConnection->createQueryBuilder()
            ->select([
                'id',
                "'" . ElementType::DATA_OBJECT->value . "'",
                'className',
                "'{$operation}'",
                "'{$operationTime}'",
                '0',
            ])
            ->from('objects')
            ->where('classId = :classId')
            ->andWhere('path LIKE :path')
            ->setParameters([
                'classId' => $element->getClassId(),
                'path' => $element->getRealFullPath() . '/%',
            ]);

        if ($includeElement) {
            $select
                ->orWhere('id = :id')
                ->setParameter('id', $element->getId());
        }

        return $select;
    }
}
