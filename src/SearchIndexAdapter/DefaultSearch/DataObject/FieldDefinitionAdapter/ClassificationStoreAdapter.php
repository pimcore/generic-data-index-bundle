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
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupListing;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class ClassificationStoreAdapter extends AbstractAdapter
{
    use LoggerAwareTrait;

    private ServiceResolverInterface $classificationService;

    private LanguageServiceInterface $languageService;

    #[Required]
    public function setClassificationService(ServiceResolverInterface $serviceResolver): void
    {
        $this->classificationService = $serviceResolver;
    }

    #[Required]
    public function setLanguageService(LanguageServiceInterface $languageService): void
    {
        $this->languageService = $languageService;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getIndexMapping(): array
    {
        $classificationStore = $this->getClassificationStoreDefinition();
        $mapping = [];

        $groups = $this->getClassificationStoreGroups($classificationStore->getStoreId());
        foreach ($groups as $group) {
            $groupName = $this->normalizeNameSegment($group->getName(), 'group', $group->getId());
            if ($groupName === null) {
                continue;
            }

            $keys = $this->getClassificationStoreKeysFromGroup($group);
            $mapping[$groupName]['properties'] = $this->getMappingForGroupConfig($keys);
        }

        return [
            'type' => AttributeType::NESTED,
            'properties' => $mapping,
        ];
    }

    public function normalize(mixed $value): ?array
    {
        if (!$value instanceof ClassificationstoreModel) {
            return null;
        }

        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Classificationstore) {
            return null;
        }

        $validLanguages = $this->getValidLanguages($fieldDefinition);
        $resultItems = [];

        foreach ($this->getActiveGroups($value) as $groupId => $groupConfig) {
            $groupName = $this->normalizeNameSegment($groupConfig->getName(), 'group', $groupId);
            if ($groupName === null) {
                continue;
            }

            $resultItems[$groupName] = [];
            $keys = $this->getClassificationStoreKeysFromGroup($groupConfig);
            foreach ($validLanguages as $validLanguage) {
                foreach ($keys as $key) {
                    $keyName = $this->normalizeNameSegment($key->getName(), 'key', $key->getKeyId());
                    if ($keyName === null) {
                        continue;
                    }

                    $normalizedValue = $this->getNormalizedValue($value, $groupId, $key, $validLanguage);

                    if ($normalizedValue !== null) {
                        $resultItems[$groupName][$validLanguage][$keyName] = $normalizedValue;
                    }
                }
            }
        }

        return $resultItems;
    }

    /**
     * @throws Exception
     */
    public function getInheritedData(
        Concrete $dataObject,
        int $objectId,
        mixed $value,
        string $key,
        ?string $language = null,
        ?callable $callback = null
    ): array {
        $classificationStore = $this->getClassificationStoreDefinition();
        $languages = $this->getValidLanguages($classificationStore);
        $result = [];
        foreach ($this->getMappingForInheritance($dataObject, $classificationStore) as $groupId => $group) {
            foreach ($group['keys'] as $keyId => $groupKey) {
                foreach ($languages as $lang) {
                    $originId = $this->getKeyValueFromElement(
                        $groupKey['definition'],
                        $dataObject,
                        $key,
                        $groupId,
                        $keyId,
                        $lang
                    );

                    if ($originId !== null && $originId !== $objectId) {
                        $result[$this->getInheritancePath($key, $group['name'], $groupKey['name'], $lang)] = [
                            'originId' => $originId,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getClassificationStoreDefinition(): Classificationstore
    {
        $classificationStore = $this->getFieldDefinition();
        if (!$classificationStore instanceof Classificationstore) {
            throw new InvalidArgumentException(
                'Field definition must be an instance of ' . Classificationstore::class
            );
        }

        return $classificationStore;
    }

    /**
     * @throws Exception
     */
    private function getKeyValueFromElement(
        Data $definition,
        Concrete $dataObject,
        string $storeKey,
        int $groupId,
        int $groupKeyId,
        string $language
    ): ?int {
        $data = $dataObject->get($storeKey)->getLocalizedKeyValue($groupId, $groupKeyId, $language, true, true);

        if (!$definition->isEmpty($data)) {
            return $dataObject->getId();
        }

        $parent = $dataObject->getNextParentForInheritance();
        if ($parent === null) {
            return $dataObject->getId();
        }

        return $this->getKeyValueFromElement($definition, $parent, $storeKey, $groupId, $groupKeyId, $language);
    }

    private function getValidLanguages(Classificationstore $classificationStore): array
    {
        $languages = [MappingProperty::NOT_LOCALIZED_KEY];
        if ($classificationStore->isLocalized()) {
            $languages = array_merge($languages, $this->languageService->getValidLanguages());
        }

        return $languages;
    }

    private function getElementActiveGroups(Concrete $dataObject, Classificationstore $classificationStore): array
    {
        $activeGroups = [];
        foreach ($classificationStore->recursiveGetActiveGroupsIds($dataObject) as $groupId => $active) {
            if ($active) {
                $activeGroups[] = $groupId;
            }
        }

        return $activeGroups;
    }

    /**
     * The search index rejects an object field name that starts or ends with a dot - "object field
     * starting or ending with a [.] makes object resolution ambiguous" - because the dot is its path
     * separator. A classification store group or key name may carry one, and a single such name makes
     * the whole bulk request fail, so no element of the index gets updated at all.
     *
     * A name that neither starts nor ends with a dot is returned unchanged, so nothing that indexes
     * today is affected. A name consisting only of dots cannot be represented at all and is skipped.
     */
    private function normalizeNameSegment(string $name, string $type, int|string|null $id): ?string
    {
        $normalized = trim($name, '.');

        if ($normalized === $name) {
            return $normalized;
        }

        if ($normalized === '') {
            $this->logger->warning(sprintf(
                'Skipping classification store %s %s: its name "%s" consists only of dots, ' .
                'which cannot be used as a search index field name.',
                $type,
                (string) $id,
                $name
            ));

            return null;
        }

        $this->logger->info(sprintf(
            'Classification store %s %s is indexed as "%s": its name "%s" starts or ends with a dot, ' .
            'which the search index does not allow in a field name.',
            $type,
            (string) $id,
            $normalized,
            $name
        ));

        return $normalized;
    }

    private function getInheritancePath(string $key, string $groupName, string $groupKeyName, string $lang): string
    {
        $path = $key . '.' . $groupName . '.' . $groupKeyName;
        if ($lang !== MappingProperty::NOT_LOCALIZED_KEY) {
            $path .= '.' . $lang;
        }

        return $path;
    }

    private function getMappingForInheritance(
        Concrete $dataObject,
        Classificationstore $classificationStore
    ): array {
        $mapping = [];
        $groups = $this->getClassificationStoreGroups($classificationStore->getStoreId());
        $activeGroups = $this->getElementActiveGroups($dataObject, $classificationStore);

        if (empty($activeGroups)) {
            return $mapping;
        }

        foreach ($groups as $group) {
            if (!in_array($group->getId(), $activeGroups, true)) {
                continue;
            }

            $groupName = $this->normalizeNameSegment($group->getName(), 'group', $group->getId());
            if ($groupName === null) {
                continue;
            }

            $mapping[$group->getId()] = [
                'name' => $groupName,
            ];
            $keys = $this->getClassificationStoreKeysFromGroup($group);
            foreach ($keys as $groupKey) {
                $definition = $this->getFieldDefinitionForKey($groupKey);
                if ($definition === null) {
                    continue;
                }
                $keyName = $this->normalizeNameSegment($groupKey->getName(), 'key', $groupKey->getKeyId());
                if ($keyName === null) {
                    continue;
                }

                $mapping[$groupKey->getGroupId()]['keys'][$groupKey->getKeyId()] = [
                    'name' => $keyName,
                    'definition' => $definition,
                ];
            }
        }

        return $mapping;
    }

    /**
     * @return GroupConfig[]
     */
    private function getActiveGroups(ClassificationstoreModel $value): array
    {
        $groups = [];
        foreach ($value->getActiveGroups() as $groupId => $active) {
            if ($active) {
                $groupConfig = GroupConfig::getById($groupId);
                if ($groupConfig) {
                    $groups[$groupId] = $groupConfig;
                }
            }
        }

        return $groups;
    }

    /**
     * @param KeyGroupRelation[] $groupConfigs
     */
    private function getMappingForGroupConfig(array $groupConfigs): array
    {
        $groupMapping = [];
        foreach ($groupConfigs as $key) {
            $definition = $this->getFieldDefinitionForKey($key);
            if ($definition === null) {
                continue;
            }

            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($definition);

            $keyName = $this->normalizeNameSegment($key->getName(), 'key', $key->getKeyId());

            if ($adapter && $keyName !== null) {
                $groupMapping['default']['properties'][$keyName] = $adapter->getIndexMapping();
            }
        }

        return $groupMapping;
    }

    private function getFieldDefinitionForKey(KeyGroupRelation $key): ?Data
    {
        try {
            $definition = $this->classificationService->getFieldDefinitionFromKeyConfig($key);
        } catch (Exception $e) {
            $this->logger->warning(sprintf(
                'Could not get field definition for type %s for key %s in group %s: %s',
                $key->getType(),
                $key->getKeyId(),
                $key->getGroupId(),
                $e->getMessage()
            ));

            return null;
        }

        if ($definition instanceof Data) {
            return $definition;
        }

        return null;
    }

    /**
     * @return GroupConfig[]
     */
    private function getClassificationStoreGroups(int $id): array
    {
        $listing = new GroupListing();
        $listing->setCondition('storeId = :storeId', ['storeId' => $id]);

        return $listing->getList();
    }

    /**
     * @return KeyGroupRelation[]
     */
    private function getClassificationStoreKeysFromGroup(GroupConfig $groupConfig): array
    {
        $listing = new KeyGroupRelationListing();
        $listing->addConditionParam('groupId = ?', $groupConfig->getId());

        return $listing->getList();
    }

    private function getNormalizedValue(
        ClassificationstoreModel $classificationstore,
        int $groupId,
        KeyGroupRelation $key,
        string $language
    ): mixed {
        try {
            $value = $classificationstore->getLocalizedKeyValue(
                $groupId,
                $key->getKeyId(),
                $language,
                true,
                true
            );
        } catch (Exception $exception) {
            $this->logger->warning(sprintf(
                'Could not get localized value for key %s in group %s: %s',
                $key->getKeyId(),
                $groupId,
                $exception->getMessage()
            ));

            return null;
        }

        $keyConfig = DefinitionCache::get($key->getKeyId());
        if ($keyConfig === null) {
            return null;
        }

        $fieldDefinition = $this->classificationService->getFieldDefinitionFromKeyConfig($keyConfig);

        return $this->fieldDefinitionService->normalizeValue($fieldDefinition, $value);
    }
}
