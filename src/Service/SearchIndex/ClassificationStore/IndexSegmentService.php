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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore;

use function array_filter;
use Exception;
use function explode;
use function implode;
use function is_array;
use function json_decode;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\GroupConfigResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\KeyConfigResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineFeature;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use ReflectionProperty;
use function str_contains;

/**
 * The only place the bundle turns a group or a key into a path segment. The mapping and the
 * document do not restate the rule, they ask here, so the three cannot drift apart.
 *
 * @internal the contract is {@see IndexSegmentServiceInterface}, which is not internal; this class is
 */
final readonly class IndexSegmentService implements IndexSegmentServiceInterface
{
    private const PATH_SEPARATOR = '.';

    private const VARIANT_SEPARATOR = '_';

    private const DEFINITION_INDEXED = 'indexed';

    public function __construct(
        private GroupConfigResolverInterface $groupConfigResolver,
        private KeyConfigResolverInterface $keyConfigResolver,
        private StoreConfigReaderInterface $storeConfigReader,
        private ServiceResolverInterface $classificationService,
        private FieldDefinitionServiceInterface $fieldDefinitionService,
        private EngineCapabilityServiceInterface $engineCapabilityService,
    ) {
    }

    public function getGroupSegment(int $groupId): ?string
    {
        $group = $this->groupConfigResolver->getById($groupId);

        return $group === null ? null : $this->getSegment($group->getIdentifier(), $group->getName());
    }

    public function getKeySegment(int $keyId, ?string $variant = null): ?string
    {
        $key = $this->keyConfigResolver->getById($keyId);
        if ($key === null) {
            return null;
        }

        $segment = $this->getSegment($key->getIdentifier(), $key->getName());
        if ($segment === null || $variant === null) {
            return $segment;
        }

        $variantSegment = $this->normalizeSegment($variant);

        return $variantSegment === null ? null : $segment . self::VARIANT_SEPARATOR . $variantSegment;
    }

    public function getGroupEntries(int $groupId): array
    {
        $group = $this->groupConfigResolver->getById($groupId);
        if ($group === null) {
            return [];
        }

        $entries = [];
        $reserved = [];

        foreach ($this->storeConfigReader->getKeyRelations($groupId) as $key) {
            $this->collectKeyEntries($entries, $reserved, $key, $group->getStoreId());
        }

        return $entries;
    }

    /**
     * An identifier is a dot-free segment of its own, so only a name is ever normalized: ".A" and
     * "A." become "A", "A..B" becomes "A.B", and a name of nothing but dots has no segment left.
     */
    public function normalizeSegment(string $name): ?string
    {
        // the all-dot case with the dots left out: nothing is left to address either
        if ($name === '') {
            return null;
        }

        if (!str_contains($name, self::PATH_SEPARATOR)) {
            return $name;
        }

        $normalized = implode(
            self::PATH_SEPARATOR,
            array_filter(explode(self::PATH_SEPARATOR, $name), static fn (string $part): bool => $part !== '')
        );

        return $normalized === '' ? null : $normalized;
    }

    /** The identifier, or the name of an entity that has none - a row no backfill has reached yet. */
    private function getSegment(?string $identifier, string $name): ?string
    {
        return $identifier ?? $this->normalizeSegment($name);
    }

    /**
     * The entries one key assignment adds to the group, in the order and under the exclusions
     * {@see \Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter::getDocumentEntryNames()}
     * writes them, so what this answers is what the document has and not what a key might deserve.
     *
     * $reserved is wider than $entries on purpose: a segment the document declares but never fills -
     * the bare segment of a key that declines the unqualified value - still costs a later key of the
     * same normalized name its entry there, which is the collision the document resolves the same way.
     *
     * @param array<string, ClassificationStoreEntry> $entries
     * @param array<string, true> $reserved the segments the earlier keys of this group took
     */
    private function collectKeyEntries(
        array &$entries,
        array &$reserved,
        KeyGroupRelation $key,
        int $storeId
    ): void {
        if (!$this->isIndexedKey($key)) {
            return;
        }

        $definition = $this->getKeyDefinition($key);
        if (!$this->isIndexableDefinition($definition, $storeId)) {
            return;
        }

        $keyId = $key->getKeyId();
        $segment = $this->getKeySegment($keyId);
        if ($segment === null || isset($reserved[$segment])) {
            return;
        }

        // the bare segment is reserved before the siblings are named, so no sibling can take it
        $reserved[$segment] = true;
        if ($this->writesBareEntry($definition)) {
            $entries[$segment] = new ClassificationStoreEntry($keyId, null, $key->getName());
        }

        foreach ($this->getDeclaredVariants($definition) as $variant) {
            $sibling = $this->getKeySegment($keyId, $variant);
            if ($sibling === null || isset($reserved[$sibling])) {
                continue;
            }

            $reserved[$sibling] = true;
            $entries[$sibling] = new ClassificationStoreEntry($keyId, $variant, $key->getName());
        }
    }

    /**
     * Whether the key is indexed at all: the `indexed` flag of its own definition, which excludes it
     * from every group it is assigned to. A definition that does not decode into an array, or that
     * carries no such entry, means indexed - a definition this bundle cannot read must not silently
     * drop a key. A relation that was never hydrated answers no: both properties are typed without a
     * default, so reading them off it is an Error rather than a value.
     */
    private function isIndexedKey(KeyGroupRelation $key): bool
    {
        if (!$this->isHydratedRelationProperty($key, 'keyId')
            || !$this->isHydratedRelationProperty($key, 'definition')
        ) {
            return false;
        }

        $definition = json_decode($key->getDefinition(), true);

        return !is_array($definition) || ($definition[self::DEFINITION_INDEXED] ?? true) !== false;
    }

    private function isHydratedRelationProperty(KeyGroupRelation $key, string $property): bool
    {
        return (new ReflectionProperty(KeyGroupRelation::class, $property))->isInitialized($key);
    }

    /** Null for a key whose definition cannot be resolved, which the document path tolerates. */
    private function getKeyDefinition(KeyGroupRelation $key): ?Data
    {
        try {
            return $this->classificationService->getFieldDefinitionFromKeyConfig($key);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * The two conditions the document gates a resolved definition on: the index has an adapter for the
     * value type, and the engine behind the store can hold what the key declares. A key failing either
     * writes no entry and takes no segment. A definition that did not resolve is not gated here, for
     * the reason the document does not gate it: its value path reads the definition from the cache
     * instead, so the key still writes.
     */
    private function isIndexableDefinition(?Data $definition, int $storeId): bool
    {
        if ($definition === null) {
            return true;
        }

        if ($this->fieldDefinitionService->getFieldDefinitionAdapter($this->getValueDefinition($definition)) === null) {
            return false;
        }

        return !$definition instanceof MultiValue
            || $definition->getVariantNames() === []
            || $this->engineCapabilityService->supports($storeId, EngineFeature::Variants);
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
     * Whether the key writes its bare entry: the unqualified slot, which every key but a `multiValue`
     * declining it holds. A declining key is addressed only through its variant siblings.
     */
    private function writesBareEntry(?Data $definition): bool
    {
        return !$definition instanceof MultiValue || $definition->permitsUnqualifiedValue();
    }

    /**
     * The variants the key declares, each of which owns a `<key>_<variant>` sibling entry.
     *
     * @return string[]
     */
    private function getDeclaredVariants(?Data $definition): array
    {
        return $definition instanceof MultiValue ? $definition->getVariantNames() : [];
    }
}
