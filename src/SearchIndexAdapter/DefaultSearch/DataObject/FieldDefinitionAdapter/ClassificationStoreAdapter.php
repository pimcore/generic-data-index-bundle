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
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class ClassificationStoreAdapter extends AbstractAdapter
{
    use LoggerAwareTrait;

    private const DEFINITION_INDEXED = 'indexed';

    private ServiceResolverInterface $classificationService;

    private LanguageServiceInterface $languageService;

    private LocalizationResolver $localizationResolver;

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

    #[Required]
    public function setLocalizationResolver(LocalizationResolver $localizationResolver): void
    {
        $this->localizationResolver = $localizationResolver;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getIndexMapping(): array
    {
        $classificationStore = $this->getClassificationStoreDefinition();
        $validLanguages = $this->getValidLanguages();
        $mapping = [];

        $groups = $this->filterAllowedGroups(
            $this->getClassificationStoreGroups($classificationStore->getStoreId()),
            $classificationStore
        );
        foreach ($groups as $group) {
            $groupName = $this->normalizeNameSegment($group->getName(), 'group', $group->getId(), true);
            if ($groupName === null
                || $this->isTakenNameSegment($mapping, $groupName, $group->getName(), 'group', $group->getId(), true)
            ) {
                continue;
            }

            $keys = $this->getClassificationStoreKeysFromGroup($group);
            $mapping[$groupName]['properties'] = $this->getMappingForGroupConfig($keys, $validLanguages);
        }

        return $this->getNestedRootMapping($mapping);
    }

    /**
     * The declared languages are the ones normalize() writes, so every indexed path is declared.
     * "dynamic: false" keeps an undeclared group, language or key in _source without indexing it and
     * without failing the bulk request, instead of letting the engine guess its type.
     */
    private function getNestedRootMapping(array $groupMapping): array
    {
        return [
            'type' => AttributeType::NESTED,
            'dynamic' => false,
            'properties' => $groupMapping,
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

        $validLanguages = $this->getValidLanguages();
        $resultItems = [];

        foreach ($this->getActiveGroups($value) as $groupId => $groupConfig) {
            $groupName = $this->normalizeNameSegment($groupConfig->getName(), 'group', $groupId);
            if ($groupName === null
                || $this->isTakenNameSegment($resultItems, $groupName, $groupConfig->getName(), 'group', $groupId)
            ) {
                continue;
            }

            $resultItems[$groupName] = $this->normalizeGroupValues(
                $value,
                $groupId,
                $this->getClassificationStoreKeysFromGroup($groupConfig),
                $validLanguages
            );
        }

        return $resultItems;
    }

    /**
     * @param KeyGroupRelation[] $keys
     * @param string[] $validLanguages
     *
     * @throws InvalidArgumentException
     */
    private function normalizeGroupValues(
        ClassificationstoreModel $value,
        int $groupId,
        array $keys,
        array $validLanguages
    ): array {
        $normalizedGroup = [];
        $usedKeyNames = [];

        foreach ($keys as $key) {
            if (!$this->isIndexedKey($key)) {
                continue;
            }

            $keyName = $this->normalizeNameSegment($key->getName(), 'key', $key->getKeyId());
            if ($keyName === null
                || $this->isTakenNameSegment($usedKeyNames, $keyName, $key->getName(), 'key', $key->getKeyId())
            ) {
                continue;
            }

            $usedKeyNames[$keyName] = true;

            foreach ($this->getLanguagesForKey($key->getKeyId(), $validLanguages) as $keyLanguage) {
                $normalizedValue = $this->getNormalizedValue($value, $groupId, $key, $keyLanguage);

                if ($normalizedValue !== null) {
                    $normalizedGroup[$keyLanguage][$keyName] = $normalizedValue;
                }
            }
        }

        return $normalizedGroup;
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
        $languages = $this->getValidLanguages();
        $result = [];
        foreach ($this->getMappingForInheritance($dataObject, $classificationStore) as $groupId => $group) {
            foreach ($group['keys'] as $keyId => $groupKey) {
                $paths = $this->getInheritancePathsForKey(
                    $key,
                    $group['name'],
                    $groupKey['name'],
                    $keyId,
                    $languages
                );

                foreach ($paths as $lang => $path) {
                    $originId = $this->getKeyValueFromElement(
                        $groupKey['definition'],
                        $dataObject,
                        $key,
                        $groupId,
                        $keyId,
                        $lang
                    );

                    if ($originId !== null && $originId !== $objectId) {
                        $result[$path] = ['originId' => $originId];
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

    /**
     * The language set a localized key is declared and written under: the non-localized node plus
     * every valid language of the installation. There is no per key subset of languages.
     *
     * @return string[]
     */
    private function getValidLanguages(): array
    {
        return array_merge(
            [MappingProperty::NOT_LOCALIZED_KEY],
            $this->languageService->getValidLanguages()
        );
    }

    /**
     * The only place the adapter asks whether a key is localized. The rule - the key's own flag
     * decides and a key without one inherits the class definition field's - belongs to
     * {@see LocalizationResolver} and is not restated here, so the two cannot drift apart.
     *
     * @param string[] $validLanguages
     *
     * @return string[]
     *
     * @throws InvalidArgumentException
     */
    private function getLanguagesForKey(int $keyId, array $validLanguages): array
    {
        $classificationStore = $this->getClassificationStoreDefinition();

        if (!$this->localizationResolver->isKeyLocalized($keyId, $classificationStore)) {
            return [MappingProperty::NOT_LOCALIZED_KEY];
        }

        return $validLanguages;
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
     * The search index splits a field path on dots, so a name segment must not add an empty component:
     * "object field starting or ending with a [.] makes object resolution ambiguous". A classification
     * store group or key name may carry a leading, trailing or doubled dot, and a single such name makes
     * the whole bulk request fail, so no element of the index gets updated at all.
     *
     * Empty components are therefore dropped: ".A" and "A." become "A", "A..B" becomes "A.B". A name that
     * contributes no empty component is returned unchanged, so nothing that indexes today is affected. A
     * name consisting only of dots has nothing left and is skipped.
     *
     * Diagnostics are reported only from the mapping paths, which run once per index update. The document
     * paths run per element and per locale and would otherwise repeat the same line for every object in
     * the index.
     */
    private function normalizeNameSegment(
        string $name,
        string $type,
        int|string|null $id,
        bool $reportDiagnostics = false
    ): ?string {
        if (!str_contains($name, '.')) {
            return $name;
        }

        $normalized = implode('.', array_filter(explode('.', $name), static fn (string $part): bool => $part !== ''));

        if ($normalized === $name) {
            return $normalized;
        }

        if ($normalized === '') {
            if ($reportDiagnostics) {
                $this->logger->warning(sprintf(
                    'Skipping classification store %s %s: its name "%s" consists only of dots, ' .
                    'which cannot be used as a search index field name.',
                    $type,
                    (string) $id,
                    $name
                ));
            }

            return null;
        }

        if ($reportDiagnostics) {
            $this->logger->info(sprintf(
                'Classification store %s %s is indexed as "%s": its name "%s" would add an empty component ' .
                'to the field path, which the search index does not allow.',
                $type,
                (string) $id,
                $normalized,
                $name
            ));
        }

        return $normalized;
    }

    /**
     * Two names that differ only in dot placement - "A." and "A" - normalize to the same segment, and
     * classification store names are not unique, so the later one would silently overwrite the earlier
     * one in the mapping, the document and the inheritance path. The first one wins instead; all three
     * iterate the same listings in the same order, so they drop the same entry.
     */
    private function isTakenNameSegment(
        array $existingNames,
        string $normalizedName,
        string $originalName,
        string $type,
        int|string|null $id,
        bool $reportDiagnostics = false
    ): bool {
        if (!isset($existingNames[$normalizedName])) {
            return false;
        }

        if ($reportDiagnostics) {
            $this->logger->warning(sprintf(
                'Skipping classification store %s %s: its name "%s" is indexed as "%s", which is already ' .
                'used by another %s of the same store.',
                $type,
                (string) $id,
                $originalName,
                $normalizedName,
                $type
            ));
        }

        return true;
    }

    /**
     * A key marked as not indexed is dropped from the mapping, the document and the inheritance paths
     * alike, and before its name is normalized, so it does not reserve a segment another key of the same
     * normalized name could use. The flag belongs to the key, not to one of its assignments: the
     * relation table carries no definition of its own, so the definition the relation listing hands over
     * is the key's, and the flag therefore excludes the key from every group it is assigned to.
     *
     * The flag is only honoured on a definition that decodes into an array, and only as the boolean
     * false. Everything else means indexed: a definition without the entry, an empty, unparsable or
     * null one, one that decodes into a scalar, and any other value of the entry. That is wider than
     * the exclusion itself needs, and deliberately so - a definition this adapter cannot read must not
     * silently drop a key out of the index. The flag the exclusion replaced was read from a nullable
     * metadata array, where an unreadable value meant indexed for the same reason.
     */
    private function isIndexedKey(KeyGroupRelation $key): bool
    {
        $definition = json_decode($key->getDefinition(), true);
        if (!is_array($definition)) {
            return true;
        }

        return ($definition[self::DEFINITION_INDEXED] ?? true) !== false;
    }

    /**
     * The inherited field paths of one key, keyed by the language each was built for: a single path
     * for a non-localized key and one per valid language for a localized one.
     *
     * @param string[] $validLanguages
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException
     */
    private function getInheritancePathsForKey(
        string $key,
        string $groupName,
        string $groupKeyName,
        int $keyId,
        array $validLanguages
    ): array {
        $paths = [];
        foreach ($this->getLanguagesForKey($keyId, $validLanguages) as $language) {
            $paths[$language] = $this->getInheritancePath($key, $groupName, $groupKeyName, $language);
        }

        return $paths;
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

        $usedGroupNames = [];

        foreach ($groups as $group) {
            if (!in_array($group->getId(), $activeGroups, true)) {
                continue;
            }

            $groupName = $this->normalizeNameSegment($group->getName(), 'group', $group->getId());
            if ($groupName === null
                || $this->isTakenNameSegment($usedGroupNames, $groupName, $group->getName(), 'group', $group->getId())
            ) {
                continue;
            }

            $usedGroupNames[$groupName] = true;
            $mapping[$group->getId()] = [
                'name' => $groupName,
                'keys' => $this->getKeyMappingForInheritance($this->getClassificationStoreKeysFromGroup($group)),
            ];
        }

        return $mapping;
    }

    /**
     * @param KeyGroupRelation[] $keys
     *
     * @return array<int, array{name: string, definition: Data}>
     */
    private function getKeyMappingForInheritance(array $keys): array
    {
        $keyMapping = [];
        $usedKeyNames = [];

        foreach ($keys as $groupKey) {
            if (!$this->isIndexedKey($groupKey)) {
                continue;
            }

            $definition = $this->getFieldDefinitionForKey($groupKey);
            if ($definition === null) {
                continue;
            }

            $keyId = $groupKey->getKeyId();
            $keyName = $this->normalizeNameSegment($groupKey->getName(), 'key', $keyId);
            if ($keyName === null
                || $this->isTakenNameSegment($usedKeyNames, $keyName, $groupKey->getName(), 'key', $keyId)
            ) {
                continue;
            }

            $usedKeyNames[$keyName] = true;
            $keyMapping[$keyId] = [
                'name' => $keyName,
                'definition' => $definition,
            ];
        }

        return $keyMapping;
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
     * The language nodes of a group are declared per key, not per group: every indexed key is declared
     * under the non-localized node and a localized one under each valid language as well, so a group
     * holding keys of differing flag declares a different key set per node.
     *
     * @param KeyGroupRelation[] $groupConfigs
     * @param string[] $validLanguages
     *
     * @throws InvalidArgumentException
     */
    private function getMappingForGroupConfig(array $groupConfigs, array $validLanguages): array
    {
        $groupMapping = [];
        $usedKeyNames = [];

        foreach ($groupConfigs as $key) {
            if (!$this->isIndexedKey($key)) {
                continue;
            }

            $definition = $this->getFieldDefinitionForKey($key);
            if ($definition === null) {
                continue;
            }

            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($definition);

            $keyName = $this->normalizeNameSegment($key->getName(), 'key', $key->getKeyId(), true);

            if ($adapter === null || $keyName === null) {
                continue;
            }

            if ($this->isTakenNameSegment($usedKeyNames, $keyName, $key->getName(), 'key', $key->getKeyId(), true)) {
                continue;
            }

            $usedKeyNames[$keyName] = true;
            // once per key: the mapping of the key does not depend on the language
            $keyMapping = $adapter->getIndexMapping();

            foreach ($this->getLanguagesForKey($key->getKeyId(), $validLanguages) as $language) {
                $groupMapping[$language]['properties'][$keyName] = $keyMapping;
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
     * Every group is mapped with all of its keys (and languages), so mapping groups that an object of this
     * class cannot get assigned can exceed the index total fields limit. When the field restricts the
     * allowed groups, only those are mapped; without a restriction, all groups of the store stay mapped.
     * The property has no default and stays uninitialized for definitions that never set it.
     *
     * This filter does not reach the document: a group that is active on the object but not allowed by the
     * field stays in _source, where it is still readable, and "dynamic: false" keeps it out of the index -
     * out of this mapping, that is: an in-place update merges into the existing one and leaves a node that
     * dynamic mapping created there until the index is built from scratch. A key marked as not indexed on
     * its own definition is the exclusion that does reach the document, see {@see self::isIndexedKey()}.
     *
     * @param GroupConfig[] $groups
     *
     * @return GroupConfig[]
     */
    private function filterAllowedGroups(array $groups, Classificationstore $classificationStore): array
    {
        if (!isset($classificationStore->allowedGroupIds) || $classificationStore->getAllowedGroupIds() === []) {
            return $groups;
        }

        $allowedGroupIds = array_map('intval', $classificationStore->getAllowedGroupIds());

        return array_filter(
            $groups,
            static fn (GroupConfig $group): bool => in_array($group->getId(), $allowedGroupIds, true)
        );
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
