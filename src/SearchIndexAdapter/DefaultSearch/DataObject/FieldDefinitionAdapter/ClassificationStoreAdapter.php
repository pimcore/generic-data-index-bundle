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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\EngineCapabilityServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\EncryptedField;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineFeature;
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

    private EngineCapabilityServiceInterface $engineCapabilityService;

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

    #[Required]
    public function setEngineCapabilityService(EngineCapabilityServiceInterface $engineCapabilityService): void
    {
        $this->engineCapabilityService = $engineCapabilityService;
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
            $entryNames = $this->getDocumentEntryNames($usedKeyNames, $key);
            if ($entryNames === null) {
                continue;
            }

            $usedKeyNames += array_fill_keys([$entryNames['name'], ...array_values($entryNames['variants'])], true);

            foreach ($this->getLanguagesForKey($key->getKeyId(), $validLanguages) as $keyLanguage) {
                $entries = $this->getEntriesForKey($value, $groupId, $key, $keyLanguage, $entryNames);

                foreach ($entries as $entryName => $entry) {
                    $normalizedGroup[$keyLanguage][$entryName] = $entry;
                }
            }
        }

        return $normalizedGroup;
    }

    /**
     * The entries one key owns in the document and the shape they are written in - the bare entry plus
     * a sibling per declared variant - or null for a key that writes none, which is also a key that
     * reserves no name segment.
     *
     * A key the index cannot type is refused here before its name segment is reserved, not merely left
     * valueless by {@see self::normalizeKeyValue()}: the value would be dropped either way, but a
     * reserved segment would cost a later key of the same normalized name the document entry the
     * mapping declares for it. Asked in the order the mapping asks it - name, then value adapter, then
     * the engine capability, then collision - of the definition the relation resolves to, which is the
     * one the mapping gates on. A relation that resolves to no definition at all keeps today's
     * behaviour: the value path reads its own definition from the definition cache, so that key is not
     * what this guard is about.
     *
     * @param array<string, bool> $usedKeyNames the segments the earlier keys of this group reserved
     *
     * @return array{name: string, variants: array<string, string>, multiValue: bool}|null
     *
     * @throws InvalidArgumentException
     */
    private function getDocumentEntryNames(array $usedKeyNames, KeyGroupRelation $key): ?array
    {
        if (!$this->isIndexedKey($key)) {
            return null;
        }

        $definition = $this->getFieldDefinitionForKey($key);
        $keyName = $this->normalizeNameSegment($key->getName(), 'key', $key->getKeyId());

        if ($keyName === null
            || ($definition !== null && $this->getValueAdapterForKey($definition) === null)
            || $this->getMissingEngineFeature($definition) !== null
            || $this->isTakenNameSegment($usedKeyNames, $keyName, $key->getName(), 'key', $key->getKeyId())
        ) {
            return null;
        }

        // the bare name is reserved before the siblings are named, so no sibling can take it
        $usedKeyNames[$keyName] = true;

        return [
            'name' => $keyName,
            'variants' => $this->getVariantSiblingNames($usedKeyNames, $keyName, $definition, $key),
            'multiValue' => $definition instanceof MultiValue && $definition->isMultiValue(),
        ];
    }

    /**
     * The bare entry keeps the default variant, each declared variant gets a sibling entry.
     *
     * @param array{name: string, variants: array<string, string>, multiValue: bool} $entryNames
     */
    private function getEntriesForKey(
        ClassificationstoreModel $value,
        int $groupId,
        KeyGroupRelation $key,
        string $language,
        array $entryNames
    ): array {
        $entries = [];
        $multiValue = $entryNames['multiValue'];

        $bareValue = $multiValue
            ? $this->getNormalizedValues($value, $groupId, $key, $language, null)
            : $this->getNormalizedValue($value, $groupId, $key, $language);

        if ($bareValue !== null) {
            $entries[$entryNames['name']] = $bareValue;
        }

        foreach ($entryNames['variants'] as $variantName => $siblingName) {
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
     * position 0 alone. Null when no object in the chain holds it: the document writes no value there
     * either, so no inherited path is reported for it.
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
            return null;
        }

        return $this->getKeyValueFromElement($slot, $parent, $language);
    }

    /**
     * Whether the given element's own store holds a value for the entry. The qualified reader is
     * asked only for an entry that is written from it - a key with neither a variant nor a list is
     * read the way it is read today - and a slot it refuses answers that the element holds nothing,
     * which is what {@see self::getNormalizedValues()} indexes for the same slot. "Holds a value" is
     * decided by the same predicate the document is written with - the value normalizes to something
     * other than null - so a falsy-but-present value (0, '0', false) is held here exactly where the
     * document carries it.
     *
     * The refusal is not logged here: a key the engine cannot hold never reaches this walk, as the
     * engine capability gate drops it first and the mapping path reports it once per key. A refusal that
     * still arrives, from an engine that answers the gate and the read differently, would be repeated
     * per ancestor of the inheritance walk without naming anything new.
     *
     * @throws Exception
     */
    private function holdsEntry(KeyEntrySlot $slot, Concrete $dataObject, string $language): bool
    {
        $store = $dataObject->get($slot->getStoreKey());
        if ($slot->getVariant() === null && !$slot->isMultiValue()) {
            return $this->normalizeKeyValue(
                $slot->getKeyId(),
                $store->getLocalizedKeyValue($slot->getGroupId(), $slot->getKeyId(), $language, true, true)
            ) !== null;
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
            if ($this->normalizeKeyValue($slot->getKeyId(), $value) !== null) {
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
     * A key the index cannot type contributes nothing here either: the resolved value adapter is the
     * condition the mapping gates the key on, so asking it here is what keeps an inherited path from
     * naming a document path that neither the mapping nor the document has
     * {@see self::getValueAdapterForKey()}. So does a key whose declaration the store's engine cannot
     * hold {@see self::getMissingEngineFeature()}.
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
            if ($definition === null
                || $this->getValueAdapterForKey($definition) === null
                || $this->getMissingEngineFeature($definition) !== null
            ) {
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
            $keyMappings = $this->getMappingNodesForKey($usedKeyNames, $key);
            if ($keyMappings === null) {
                continue;
            }

            $usedKeyNames += array_fill_keys(array_keys($keyMappings), true);

            foreach ($this->getLanguagesForKey($key->getKeyId(), $validLanguages) as $language) {
                $groupMapping[$language]['properties'] = ($groupMapping[$language]['properties'] ?? []) + $keyMappings;
            }
        }

        return $groupMapping;
    }

    /**
     * The mapping nodes one key declares, as the name segment each is declared under => the mapping of
     * the key's value type, or null for a key that declares none, which is also a key that reserves no
     * name segment. The nodes of a key are the same under every language node it is declared under, so
     * the value adapter is asked once per key rather than once per language.
     *
     * This is the only one of the three key-enumerating paths that reports diagnostics, because it is
     * the only one that runs once per index update rather than once per element and locale.
     *
     * @param array<string, bool> $usedKeyNames the segments the earlier keys of this group reserved
     *
     * @return array<string, array>|null
     *
     * @throws InvalidArgumentException
     */
    private function getMappingNodesForKey(array $usedKeyNames, KeyGroupRelation $key): ?array
    {
        if (!$this->isIndexedKey($key)) {
            return null;
        }

        $definition = $this->getFieldDefinitionForKey($key);
        if ($definition === null) {
            return null;
        }

        $adapter = $this->getValueAdapterForKey($definition);
        $keyName = $this->normalizeNameSegment($key->getName(), 'key', $key->getKeyId(), true);

        if ($keyName === null) {
            return null;
        }

        if ($adapter === null) {
            $valueDefinition = $this->getValueDefinition($definition);
            if (!$this->isDeliberatelyNotIndexedType($valueDefinition)) {
                $this->reportKeyWithoutAdapter($key, $valueDefinition);
            }

            return null;
        }

        $missingFeature = $this->getMissingEngineFeature($definition);
        if ($missingFeature !== null) {
            $this->reportKeyWithoutEngineSupport($key, $missingFeature);

            return null;
        }

        if ($this->isTakenNameSegment($usedKeyNames, $keyName, $key->getName(), 'key', $key->getKeyId(), true)) {
            return null;
        }

        // the bare name is reserved before the siblings are named, so no sibling can take it
        $usedKeyNames[$keyName] = true;
        $variantNames = $this->getVariantSiblingNames($usedKeyNames, $keyName, $definition, $key, true);

        return array_fill_keys([$keyName, ...array_values($variantNames)], $adapter->getIndexMapping());
    }

    /**
     * The field definition adapter of the key's value type, which is the last condition of "this key
     * is indexed" and therefore has to be asked by all three paths: a key whose resolved value
     * definition has no adapter declares no mapping node {@see self::getMappingForGroupConfig()},
     * writes no document entry {@see self::normalizeGroupValues()} and emits no inherited field path
     * {@see self::getKeyMappingForInheritance()} - neither for the key nor for any of its variant
     * siblings, since the sibling is indexed as the same type the bare entry is.
     *
     * Asked before the key's name segment is reserved, so a key the index cannot type does not shadow
     * a later key of the same normalized name that it can, which is the ordering
     * {@see self::isTakenNameSegment()} relies on to drop the same entry in all three.
     *
     * The diagnostic is not raised here: it belongs to the mapping path alone, which runs once per
     * index update, and only to the key that survived its own name check there.
     */
    private function getValueAdapterForKey(Data $definition): ?AdapterInterface
    {
        return $this->getFieldDefinitionService()->getFieldDefinitionAdapter($this->getValueDefinition($definition));
    }

    /**
     * The capability a key's declaration needs and the engine behind the store lacks, null for a key
     * the engine can hold: a key declaring `variants` needs Variants, one declaring `multiValue` needs
     * MultiValue, and one declaring neither needs nothing, so it is unaffected on every engine. The
     * second condition of "this key is indexed" every path asks, right after the value adapter, so the
     * mapping, the document and the inheritance walk drop the same keys. The diagnostic is not raised
     * here: it belongs to the mapping path alone.
     */
    private function getMissingEngineFeature(?Data $definition): ?EngineFeature
    {
        if (!$definition instanceof MultiValue) {
            return null;
        }

        $required = [];
        if ($definition->getVariantNames() !== []) {
            $required[] = EngineFeature::Variants;
        }
        if ($definition->isMultiValue()) {
            $required[] = EngineFeature::MultiValue;
        }

        $storeId = $this->getConfiguredStoreId();
        foreach ($required as $feature) {
            if (!$this->engineCapabilityService->supports($storeId, $feature)) {
                return $feature;
            }
        }

        return null;
    }

    /** Null for a field definition that carries no store: the property is typed with no default. */
    private function getConfiguredStoreId(): ?int
    {
        $fieldDefinition = $this->getClassificationStoreDefinition();

        return isset($fieldDefinition->storeId) ? $fieldDefinition->getStoreId() : null;
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

    /**
     * A value type this bundle does not index ON PURPOSE, for which "no field definition adapter" is
     * the intended answer rather than the gap {@see self::reportKeyWithoutAdapter()} reports. Such a
     * key is dropped exactly as any other unsupported key is - it declares no mapping node, writes no
     * document entry and emits no inherited path - it is just not reported while being dropped.
     *
     * `encryptedField` is the whole list, and it is here because the diagnostic would otherwise turn a
     * correct exclusion into an error on every index update. This bundle has never had an adapter for
     * the type - nothing under `src/` or `config/` names it - so an encrypted key has always been
     * skipped silently, which is the right outcome: the value of such a key is a secret and indexing it
     * would publish it in a searchable, filterable and `_source`-readable form. Core's
     * {@see \Pimcore\Model\DataObject\Classificationstore\Service::getValueDefinition()} draws the same
     * line from the other side - it stops ON an `encryptedField` rather than unwrapping through it,
     * "that wrapper carries the stored form itself" - so the definition that reaches this check is the
     * `encryptedField` itself and not the delegate whose adapter would have typed the plaintext.
     *
     * The key is reachable, which is why the exclusion has to be stated rather than assumed: core's
     * {@see \Pimcore\Model\DataObject\Classificationstore\KeyTypeSupport} classes `encryptedField` as a
     * WRAPPER type, not an excluded one, so a key may be defined with it. A `multiValue` wrapping one
     * arrives here too, since {@see self::getValueDefinition()} unwraps the `multiValue` layers and
     * stops on the `encryptedField` the same way core does.
     */
    private function isDeliberatelyNotIndexedType(Data $valueDefinition): bool
    {
        return $valueDefinition instanceof EncryptedField;
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

    /** Logged, not thrown: IndexMappingService would drop the whole field on an exception. */
    private function reportKeyWithoutEngineSupport(KeyGroupRelation $key, EngineFeature $missingFeature): void
    {
        $this->logger->warning(sprintf(
            'Classification store %s: key %d "%s" of group %s is declared with a capability the engine of the ' .
            'store lacks (%s). The key is not indexed.',
            $this->getConfiguredStoreId() ?? 'default',
            $key->getKeyId(),
            $key->getName(),
            $this->isHydratedRelationProperty($key, 'groupId') ? (string) $key->getGroupId() : 'unknown',
            $missingFeature->name
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

        return $this->normalizeKeyValue($key->getKeyId(), $value);
    }

    /**
     * The normalized values of one (language, variant) slot at their stored positions, null when empty.
     * A position the slot holds nothing at, or whose value normalizes to null, stays in the list as null
     * up to the last position that holds a value, so no later value moves up.
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

        $normalized = [];
        foreach ($values as $position => $value) {
            $normalizedValue = $this->normalizeKeyValue($key->getKeyId(), $value);
            if ($normalizedValue !== null) {
                $normalized[(int) $position] = $normalizedValue;
            }
        }

        if ($normalized === []) {
            return null;
        }

        $list = [];
        for ($position = 0; $position <= max(array_keys($normalized)); $position++) {
            $list[] = $normalized[$position] ?? null;
        }

        return $list;
    }

    private function normalizeKeyValue(int $keyId, mixed $value): mixed
    {
        $keyConfig = DefinitionCache::get($keyId);
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
