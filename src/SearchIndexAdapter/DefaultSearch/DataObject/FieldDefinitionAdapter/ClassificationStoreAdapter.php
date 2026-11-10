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
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\ClassificationStore\KeyEntrySlot;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupListing;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;
use Pimcore\Model\DataObject\Concrete;
use ReflectionProperty;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class ClassificationStoreAdapter extends AbstractAdapter
{
    use LoggerAwareTrait;

    private const DEFINITION_INDEXED = 'indexed';

    private const CACHE_KEY_INDEXED = 'gdi_cs_key_indexed_%d';

    private ServiceResolverInterface $classificationService;

    private LanguageServiceInterface $languageService;

    private KeyLocaleServiceInterface $keyLocaleService;

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
    public function setKeyLocaleService(KeyLocaleServiceInterface $keyLocaleService): void
    {
        $this->keyLocaleService = $keyLocaleService;
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

            $definition = $this->getFieldDefinitionForKey($key);
            $variantNames = $this->getVariantSiblingNames($usedKeyNames, $keyName, $definition, $key);
            foreach ($variantNames as $siblingName) {
                $usedKeyNames[$siblingName] = true;
            }

            $multiValue = $definition instanceof MultiValue && $definition->isMultiValue();

            foreach ($this->getLanguagesForKey($key->getKeyId(), $validLanguages) as $keyLanguage) {
                $entries = $this->getEntriesForKey(
                    $value,
                    $groupId,
                    $key,
                    $keyLanguage,
                    $keyName,
                    $variantNames,
                    $multiValue
                );

                foreach ($entries as $entryName => $entry) {
                    $normalizedGroup[$keyLanguage][$entryName] = $entry;
                }
            }
        }

        return $normalizedGroup;
    }

    /**
     * The bare entry keeps the default variant, each declared variant gets a sibling entry.
     *
     * @param array<string, string> $variantNames declared variant name => sibling entry name
     */
    private function getEntriesForKey(
        ClassificationstoreModel $value,
        int $groupId,
        KeyGroupRelation $key,
        string $language,
        string $keyName,
        array $variantNames,
        bool $multiValue
    ): array {
        $entries = [];

        $bareValue = $multiValue
            ? $this->getNormalizedValues($value, $groupId, $key, $language, null)
            : $this->getNormalizedValue($value, $groupId, $key, $language);

        if ($bareValue !== null) {
            $entries[$keyName] = $bareValue;
        }

        foreach ($variantNames as $variantName => $siblingName) {
            $variantValues = $this->getNormalizedValues($value, $groupId, $key, $language, (string) $variantName);

            if ($variantValues !== null) {
                $entries[$siblingName] = $multiValue ? $variantValues : $variantValues[0];
            }
        }

        return $entries;
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
                foreach ($this->getEntryVariants($groupKey) as $entryName => $variant) {
                    $slot = new KeyEntrySlot(
                        definition: $groupKey['definition'],
                        storeKey: $key,
                        groupId: $groupId,
                        keyId: $keyId,
                        variant: $variant,
                        multiValue: $groupKey['multiValue'],
                    );
                    $paths = $this->getInheritancePathsForKey($key, $group['name'], $entryName, $keyId, $languages);

                    $result = array_replace(
                        $result,
                        $this->getInheritedPathsForEntry($slot, $dataObject, $objectId, $paths)
                    );
                }
            }
        }

        return $result;
    }

    /**
     * The inherited paths of one entry: the path of every language node the entry is written under
     * whose value this element does not hold itself, pointing at the ancestor that does.
     *
     * @param array<string, string> $paths language => the inherited field path built for it
     *
     * @return array<string, array{originId: int}>
     *
     * @throws Exception
     */
    private function getInheritedPathsForEntry(
        KeyEntrySlot $slot,
        Concrete $dataObject,
        int $objectId,
        array $paths
    ): array {
        $inherited = [];

        foreach ($paths as $language => $path) {
            $originId = $this->getKeyValueFromElement($slot, $dataObject, $language);

            if ($originId !== null && $originId !== $objectId) {
                $inherited[$path] = ['originId' => $originId];
            }
        }

        return $inherited;
    }

    /**
     * Every document entry of one key, as entry name => the variant it holds: the bare entry holds
     * the default variant and answers null for it, each sibling the variant it was declared for.
     *
     * @param array{name: string, variants: array<string, string>} $groupKey
     *
     * @return array<string, string|null>
     */
    private function getEntryVariants(array $groupKey): array
    {
        $entries = [$groupKey['name'] => null];

        foreach ($groupKey['variants'] as $variantName => $siblingName) {
            $entries[$siblingName] = (string) $variantName;
        }

        return $entries;
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
     * The nearest ancestor holding the slot this entry is written from, which is the slot the entry
     * is written from and no other: a sibling's provenance is resolved against its own variant, and
     * a `multiValue` entry against the whole list the bare entry carries rather than against
     * position 0 alone.
     *
     * @throws Exception
     */
    private function getKeyValueFromElement(KeyEntrySlot $slot, Concrete $dataObject, string $language): ?int
    {
        if ($this->holdsEntry($slot, $dataObject, $language)) {
            return $dataObject->getId();
        }

        $parent = $dataObject->getNextParentForInheritance();
        if ($parent === null) {
            return $dataObject->getId();
        }

        return $this->getKeyValueFromElement($slot, $parent, $language);
    }

    /**
     * Whether the given element's own store holds a value for the entry. The qualified reader is
     * asked only for an entry that is written from it - a key with neither a variant nor a list is
     * read the way it is read today - and a slot it refuses answers that the element holds nothing,
     * which is what {@see self::getNormalizedValues()} indexes for the same slot.
     *
     * The refusal is not logged here: a store whose engine cannot address variants raises it for
     * every variant of every such key, and the write path above already reports that slot of this
     * object in the same index run. Reporting it again per ancestor of the inheritance walk would
     * multiply one configuration mistake by the depth of the tree without naming anything new.
     *
     * @throws Exception
     */
    private function holdsEntry(KeyEntrySlot $slot, Concrete $dataObject, string $language): bool
    {
        $store = $dataObject->get($slot->getStoreKey());
        $definition = $slot->getDefinition();

        if ($slot->getVariant() === null && !$slot->isMultiValue()) {
            return !$definition->isEmpty(
                $store->getLocalizedKeyValue($slot->getGroupId(), $slot->getKeyId(), $language, true, true)
            );
        }

        try {
            $values = $store->getKeyValues(
                $slot->getGroupId(),
                $slot->getKeyId(),
                $language,
                $slot->getVariant()
            );
        } catch (Exception) {
            return false;
        }

        foreach ($values as $value) {
            if (!$definition->isEmpty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The candidate set every key picks its own language nodes from: the non-localized node plus
     * every valid language of the installation. What a key is declared and written under is the
     * subset {@see self::getLanguagesForKey()} resolves from it - the non-localized node alone for a
     * key that is not localized. There is no per key subset of languages.
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
     * The language nodes one key is declared and written under, resolved from the candidate set: the
     * non-localized node alone for a key that is not localized, that node plus every valid language
     * for one that is. The only place the adapter asks about a key's languages, and it asks the
     * {@see KeyLocaleServiceInterface} whose other method answers a caller filtering that key, so the
     * write side and the read side cannot disagree about where the key is indexed.
     *
     * @param string[] $validLanguages
     *
     * @return string[]
     *
     * @throws InvalidArgumentException
     */
    private function getLanguagesForKey(int $keyId, array $validLanguages): array
    {
        return $this->keyLocaleService->getLocalesForKey(
            $keyId,
            $this->getClassificationStoreDefinition(),
            $validLanguages
        );
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
     * What this method tolerates is a definition it cannot read: the flag is honoured only on one that
     * decodes into an array, and only as the boolean false, so a definition without the entry, an empty
     * or unparsable one, one that decodes into a JSON scalar and any other value of the entry all mean
     * indexed. That is wider than the exclusion itself needs, and deliberately so - a definition this
     * adapter cannot read must not silently drop a key out of the index.
     *
     * What it does not tolerate is a relation that was never hydrated. KeyGroupRelation declares both
     * "keyId" and "definition" as typed properties without a default, so reading either off such a
     * relation is an Error rather than a value to fall back on - the getter is not nullable and has
     * nothing to return. Neither is read before it is known to be there, and a relation that cannot
     * answer is skipped, which is what all three callers do with a key they cannot map.
     *
     * The resolved flag is a constant of the key, so it is memoized per key id - the whole of its
     * scope, since two assignments of one key read one definition and cannot disagree. normalize()
     * gets a new adapter per indexed object and visits every key of every active group, so the
     * definition would otherwise be parsed again for every object. Runtime-cache scoped like the
     * inheritance origin memo of the base adapter, so the cleanup between queue batches bounds
     * staleness. An unhydrated relation is not memoized: that says nothing about the key.
     *
     * Both properties are checked before the memo is consulted, not just the one the cache key is
     * built from. The memoized answer is about the key, but "true" is also a promise to the callers
     * that this relation can be passed to definition resolution, and a second assignment of a
     * memoized key can be unhydrated where the first was not. Reading the cache on the strength of
     * "keyId" alone would hand such a relation back as indexed and have the caller read "definition"
     * off it - the Error this method exists to prevent, reached through the memo instead of past it.
     * The guards are two reflection calls and the memo still saves the decode, which is its point.
     */
    private function isIndexedKey(KeyGroupRelation $key): bool
    {
        if (!$this->isHydratedRelationProperty($key, 'keyId')
            || !$this->isHydratedRelationProperty($key, 'definition')
        ) {
            return false;
        }

        $cacheKey = sprintf(self::CACHE_KEY_INDEXED, $key->getKeyId());
        if ($this->runtimeCacheResolver->isRegistered($cacheKey)) {
            return $this->runtimeCacheResolver->load($cacheKey);
        }

        $definition = json_decode($key->getDefinition(), true);
        $indexed = !is_array($definition) || ($definition[self::DEFINITION_INDEXED] ?? true) !== false;

        $this->runtimeCacheResolver->save($indexed, $cacheKey);

        return $indexed;
    }

    private function isHydratedRelationProperty(KeyGroupRelation $key, string $property): bool
    {
        return (new ReflectionProperty(KeyGroupRelation::class, $property))->isInitialized($key);
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
     * The entries a key owns in the document, which is what the inheritance side has to enumerate:
     * the bare one plus a sibling per declared variant, named and de-duplicated by the same rules
     * {@see self::getMappingForGroupConfig()} and {@see self::normalizeGroupValues()} apply, so a
     * sibling the mapping and the document agree on is the sibling an inherited path is built for.
     *
     * @param KeyGroupRelation[] $keys
     *
     * @return array<int, array{name: string, definition: Data, multiValue: bool,
     *                          variants: array<string, string>}>
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

            $variantNames = $this->getVariantSiblingNames($usedKeyNames, $keyName, $definition, $groupKey);
            foreach ($variantNames as $siblingName) {
                $usedKeyNames[$siblingName] = true;
            }

            $keyMapping[$keyId] = [
                'name' => $keyName,
                'definition' => $definition,
                'multiValue' => $definition instanceof MultiValue && $definition->isMultiValue(),
                'variants' => $variantNames,
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

            $valueDefinition = $this->getValueDefinition($definition);
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($valueDefinition);

            $keyName = $this->normalizeNameSegment($key->getName(), 'key', $key->getKeyId(), true);

            if ($keyName === null) {
                continue;
            }

            if ($adapter === null) {
                $this->reportKeyWithoutAdapter($key, $valueDefinition);

                continue;
            }

            if ($this->isTakenNameSegment($usedKeyNames, $keyName, $key->getName(), 'key', $key->getKeyId(), true)) {
                continue;
            }

            $usedKeyNames[$keyName] = true;

            $variantNames = $this->getVariantSiblingNames($usedKeyNames, $keyName, $definition, $key, true);
            foreach ($variantNames as $siblingName) {
                $usedKeyNames[$siblingName] = true;
            }

            // once per key: the mapping of the key does not depend on the language
            $keyMapping = $adapter->getIndexMapping();
            $keyMappings = array_fill_keys([$keyName, ...array_values($variantNames)], $keyMapping);

            foreach ($this->getLanguagesForKey($key->getKeyId(), $validLanguages) as $language) {
                $groupMapping[$language]['properties'] = ($groupMapping[$language]['properties'] ?? []) + $keyMappings;
            }
        }

        return $groupMapping;
    }

    /** A multiValue key is indexed as the type it wraps. */
    private function getValueDefinition(Data $definition): Data
    {
        while ($definition instanceof MultiValue) {
            $delegate = $definition->getDelegate();
            if (!$delegate instanceof Data) {
                break;
            }

            $definition = $delegate;
        }

        return $definition;
    }

    /**
     * One sibling "<key>_<variant>" per declared variant; a name already taken is skipped, first wins.
     *
     * @param array<string, bool> $usedKeyNames
     *
     * @return array<string, string> declared variant name => sibling name
     */
    private function getVariantSiblingNames(
        array $usedKeyNames,
        string $keyName,
        ?Data $definition,
        KeyGroupRelation $key,
        bool $reportDiagnostics = false
    ): array {
        if (!$definition instanceof MultiValue) {
            return [];
        }

        $siblingNames = [];

        foreach ($definition->getVariantNames() as $variantName) {
            $variantSegment = $this->normalizeNameSegment($variantName, 'variant', $variantName, $reportDiagnostics);
            if ($variantSegment === null) {
                continue;
            }

            $siblingName = $keyName . '_' . $variantSegment;
            if ($this->isTakenNameSegment(
                $usedKeyNames,
                $siblingName,
                $key->getName() . '_' . $variantName,
                'key',
                $key->getKeyId(),
                $reportDiagnostics
            )) {
                continue;
            }

            $usedKeyNames[$siblingName] = true;
            $siblingNames[$variantName] = $siblingName;
        }

        return $siblingNames;
    }

    /** Logged, not thrown: IndexMappingService would drop the whole field on an exception. */
    private function reportKeyWithoutAdapter(KeyGroupRelation $key, Data $valueDefinition): void
    {
        $this->logger->error(sprintf(
            'Classification store %d: key %d "%s" of group %s has type "%s", for which the search index has no ' .
            'field definition adapter. The key is not indexed.',
            $this->getClassificationStoreDefinition()->getStoreId(),
            $key->getKeyId(),
            $key->getName(),
            $this->isHydratedRelationProperty($key, 'groupId') ? (string) $key->getGroupId() : 'unknown',
            $valueDefinition->getFieldType()
        ));
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
     * Every group is mapped with all of its keys, so mapping groups that an object of this
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

        return $this->normalizeKeyValue($key, $value);
    }

    /**
     * The normalized values of one (language, variant) slot in position order, null when empty.
     *
     * @return array<int, mixed>|null
     */
    private function getNormalizedValues(
        ClassificationstoreModel $classificationstore,
        int $groupId,
        KeyGroupRelation $key,
        string $language,
        ?string $variant
    ): ?array {
        try {
            $values = $classificationstore->getKeyValues($groupId, $key->getKeyId(), $language, $variant);
        } catch (Exception $exception) {
            $this->logger->warning(sprintf(
                'Could not get values for key %s in group %s: %s',
                $key->getKeyId(),
                $groupId,
                $exception->getMessage()
            ));

            return null;
        }

        ksort($values);

        $normalized = [];
        foreach ($values as $value) {
            $normalizedValue = $this->normalizeKeyValue($key, $value);
            if ($normalizedValue !== null) {
                $normalized[] = $normalizedValue;
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeKeyValue(KeyGroupRelation $key, mixed $value): mixed
    {
        $keyConfig = DefinitionCache::get($key->getKeyId());
        if ($keyConfig === null) {
            return null;
        }

        $fieldDefinition = $this->classificationService->getFieldDefinitionFromKeyConfig($keyConfig);
        if ($fieldDefinition !== null) {
            $fieldDefinition = $this->getValueDefinition($fieldDefinition);
        }

        return $this->fieldDefinitionService->normalizeValue($fieldDefinition, $value);
    }
}
