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
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
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

    private const DEFAULT_LANGUAGE = 'default';

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
            $keys = $this->getClassificationStoreKeysFromGroup($group);
            $mapping[$group->getName()]['properties'] = $this->getMappingForGroupConfig($keys);
        }

        return [
            'type' => AttributeType::NESTED,
            'properties' => $mapping,
        ];
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
        callable $callback = null
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
        $languages = [self::DEFAULT_LANGUAGE];
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

    private function getInheritancePath(string $key, string $groupName, string $groupKeyName, string $lang): string
    {
        $path = $key . '.' . $groupName . '.' . $groupKeyName;
        if ($lang !== self::DEFAULT_LANGUAGE) {
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

            $mapping[$group->getId()] = [
                'name' => $group->getName(),
            ];
            $keys = $this->getClassificationStoreKeysFromGroup($group);
            foreach ($keys as $groupKey) {
                $definition = $this->getFieldDefinitionForKey($groupKey);
                if ($definition === null) {
                    continue;
                }
                $mapping[$groupKey->getGroupId()]['keys'][$groupKey->getKeyId()] = [
                    'name' => $groupKey->getName(),
                    'definition' => $definition,
                ];
            }
        }

        return $mapping;
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

            if ($adapter) {
                $groupMapping['default']['properties'][$key->getName()] = $adapter->getIndexMapping();
            }
        }

        return $groupMapping;
    }

    private function getFieldDefinitionForKey(KeyGroupRelation $key): ?Data
    {
        try {
            $definition = $this->classificationService->getFieldDefinitionFromKeyConfig($key);
        } catch (Exception) {
            $this->logger->warning(
                'Could not get field definition for type ' . $key->getType() . ' in group ' . $key->getGroupId()
            );

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
}
