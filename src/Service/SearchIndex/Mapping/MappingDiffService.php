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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingFieldChangeAction;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\CopyMapping;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingFieldChange;

/**
 * @internal
 */
final readonly class MappingDiffService implements MappingDiffServiceInterface
{
    private const SHAPE_OBJECT = 'object';

    private const SHAPE_NESTED = 'nested';

    /** Scalar values that are stored as plain JSON scalars in the documents and fit into text/keyword. */
    private const SCALAR_TYPES = [
        'text', 'keyword', 'boolean', 'date', 'date_nanos',
        'byte', 'short', 'integer', 'long', 'unsigned_long',
        'half_float', 'float', 'double', 'scaled_float',
    ];

    private const TEXT_TYPES = ['text', 'keyword'];

    private const DATE_TYPES = ['date', 'date_nanos'];

    /** Parameters a carried (removed) field keeps; properties and fields are sanitized recursively. */
    private const CARRIED_PARAMETERS = ['type', 'format', 'scaling_factor', 'ignore_above', 'enabled', 'dynamic'];

    /** The format the engine applies to date fields without an explicit one. */
    private const DEFAULT_DATE_FORMAT = 'strict_date_optional_time||epoch_millis';

    /** Numeric widening that keeps every value (old type => accepted new types). */
    private const NUMERIC_WIDENING = [
        'byte' => ['short', 'integer', 'long', 'float', 'double'],
        'short' => ['integer', 'long', 'float', 'double'],
        'integer' => ['long', 'double'],
        'long' => ['double'],
        'half_float' => ['float', 'double'],
        'float' => ['double'],
    ];

    /**
     * @throws JsonException
     */
    public function diff(array $liveProperties, array $newProperties): MappingDiff
    {
        return new MappingDiff(
            $this->compareProperties($liveProperties, $this->asSentToTheEngine($newProperties), [])
        );
    }

    /**
     * @throws JsonException
     */
    public function mappingForCopy(array $liveProperties, array $newProperties): CopyMapping
    {
        $carriedPaths = [];
        $properties = $this->carryRemovedFields(
            $liveProperties,
            $newProperties,
            $this->asSentToTheEngine($newProperties),
            '',
            [],
            $carriedPaths
        );

        return new CopyMapping($carriedPaths === [] ? $newProperties : $properties, $carriedPaths);
    }

    /**
     * Adds every live field missing from the new mapping to $target, the new mapping as given (one
     * properties level of it). $prefix is set while a live object is matched by dotted names of the
     * new mapping ("spec.width" for spec -> width); carried fields are added the same way.
     *
     * @param array<string, mixed> $live live properties of this level
     * @param array<string, mixed> $target new properties as given, of the level the names are added to
     * @param array<string, mixed> $expanded new properties of this level, expanded like the engine does
     * @param string[] $path
     * @param string[] $carriedPaths
     *
     * @return array<string, mixed> $target with the carried fields
     */
    private function carryRemovedFields(
        array $live,
        array $target,
        array $expanded,
        string $prefix,
        array $path,
        array &$carriedPaths
    ): array {
        foreach ($live as $name => $liveNode) {
            $name = (string) $name;
            if (!is_array($liveNode)) {
                continue;
            }
            $key = $prefix . $name;
            $expandedNode = $expanded[$name] ?? null;

            if (!is_array($expandedNode)) {
                $target[$key] = $this->sanitizeCarried($liveNode);
                $carriedPaths[] = implode('.', [...$path, $name]);

                continue;
            }

            $bothContainers = $this->isContainer($this->shape($liveNode))
                && $this->isContainer($this->shape($expandedNode));
            if (!$bothContainers) {
                // A changed field: the diff strips or keeps it.
                continue;
            }

            $liveChildren = is_array($liveNode['properties'] ?? null) ? $liveNode['properties'] : [];
            $expandedChildren = is_array($expandedNode['properties'] ?? null) ? $expandedNode['properties'] : [];
            $targetNode = $target[$key] ?? null;
            if (is_array($targetNode)) {
                $children = is_array($targetNode['properties'] ?? null) ? $targetNode['properties'] : [];
                $before = count($carriedPaths);
                $children = $this->carryRemovedFields(
                    $liveChildren,
                    $children,
                    $expandedChildren,
                    '',
                    [...$path, $name],
                    $carriedPaths
                );
                if (count($carriedPaths) > $before) {
                    $targetNode['properties'] = $children;
                    $target[$key] = $targetNode;
                }

                continue;
            }

            // The object exists only through dotted names of the new mapping: stay on this level.
            $target = $this->carryRemovedFields(
                $liveChildren,
                $target,
                $expandedChildren,
                $key . '.',
                [...$path, $name],
                $carriedPaths
            );
        }

        return $target;
    }

    /**
     * Keeps what the values need to be indexed as before; drops analyzers and normalizers (they may
     * be gone from the index settings) and turns nested into object (nested_fields limit).
     */
    private function sanitizeCarried(array $node): array
    {
        $sanitized = [];
        foreach (self::CARRIED_PARAMETERS as $parameter) {
            if (array_key_exists($parameter, $node)) {
                $sanitized[$parameter] = $node[$parameter];
            }
        }
        if (($sanitized['type'] ?? null) === self::SHAPE_NESTED) {
            unset($sanitized['type']);
        }
        foreach (['properties', 'fields'] as $children) {
            if (!is_array($node[$children] ?? null) || $node[$children] === []) {
                continue;
            }
            $sanitized[$children] = array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->sanitizeCarried($child) : $child,
                $node[$children]
            );
        }

        return $sanitized;
    }

    /**
     * Field adapters may put enum cases or other serializable values into the mapping. The engine
     * receives their JSON form, so the comparison with the live mapping uses that form too.
     *
     * @throws JsonException
     */
    private function asSentToTheEngine(array $properties): array
    {
        return $this->expandDottedNames(
            json_decode(json_encode($properties, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * The engine maps a property name with dots ("spec.width", e.g. a classification store key) as
     * nested objects (spec -> width), and returns the live mapping that way.
     */
    private function expandDottedNames(array $properties): array
    {
        $expanded = [];
        foreach ($properties as $name => $node) {
            if (is_array($node) && is_array($node['properties'] ?? null)) {
                $node['properties'] = $this->expandDottedNames($node['properties']);
            }
            $expanded = $this->insertAt($expanded, explode('.', (string) $name), $node);
        }

        return $expanded;
    }

    /**
     * @param string[] $segments
     */
    private function insertAt(array $properties, array $segments, mixed $node): array
    {
        $name = (string) array_shift($segments);
        $existing = $properties[$name] ?? null;
        if ($segments === []) {
            $properties[$name] = is_array($existing) && is_array($node)
                ? array_replace_recursive($existing, $node)
                : $node;

            return $properties;
        }

        $container = is_array($existing) ? $existing : [];
        $children = is_array($container['properties'] ?? null) ? $container['properties'] : [];
        $container['properties'] = $this->insertAt($children, $segments, $node);
        $properties[$name] = $container;

        return $properties;
    }

    /**
     * @param string[] $parentPath
     *
     * @return MappingFieldChange[]
     */
    private function compareProperties(array $live, array $new, array $parentPath): array
    {
        $changes = [];
        foreach ($new as $name => $newNode) {
            $liveNode = $live[$name] ?? null;
            if (!is_array($liveNode) || !is_array($newNode)) {
                continue;
            }
            array_push($changes, ...$this->compareNode($liveNode, $newNode, [...$parentPath, (string) $name]));
        }

        return $changes;
    }

    /**
     * @param string[] $path
     *
     * @return MappingFieldChange[]
     */
    private function compareNode(array $live, array $new, array $path): array
    {
        $liveShape = $this->shape($live);
        $newShape = $this->shape($new);
        $liveIsContainer = $this->isContainer($liveShape);
        $newIsContainer = $this->isContainer($newShape);

        if ($liveIsContainer && $newIsContainer) {
            $changes = $this->compareProperties($live['properties'] ?? [], $new['properties'] ?? [], $path);
            if ($liveShape !== $newShape) {
                // object <-> nested: both hold objects (or lists of objects) in the documents.
                $changes[] = new MappingFieldChange($path, $liveShape, $newShape, MappingFieldChangeAction::KEEP);
            }

            return $changes;
        }

        if ($liveShape === $newShape) {
            return $this->compareDateFormats($live, $new, $path);
        }

        $action = !$liveIsContainer && !$newIsContainer && $this->isSafeTransition($liveShape, $newShape)
            ? MappingFieldChangeAction::KEEP
            : MappingFieldChangeAction::STRIP;

        return [new MappingFieldChange($path, $liveShape, $newShape, $action)];
    }

    /**
     * Date, datetime and time fields share the engine type date and differ only in the format.
     * The copy parses every old value with the new format, so a new format that does not accept
     * all old formats would fail the copy for each such field, one field per attempt.
     *
     * @param string[] $path
     *
     * @return MappingFieldChange[]
     */
    private function compareDateFormats(array $live, array $new, array $path): array
    {
        if (!in_array($this->shape($live), self::DATE_TYPES, true)) {
            return [];
        }

        $liveFormats = $this->dateFormats($live);
        $newFormats = $this->dateFormats($new);
        if (array_diff($liveFormats, $newFormats) === []) {
            return [];
        }

        return [new MappingFieldChange(
            $path,
            sprintf('%s [%s]', $this->shape($live), implode('||', $liveFormats)),
            sprintf('%s [%s]', $this->shape($new), implode('||', $newFormats)),
            MappingFieldChangeAction::STRIP
        )];
    }

    /**
     * @return string[]
     */
    private function dateFormats(array $node): array
    {
        $format = $node['format'] ?? null;
        if (!is_string($format) || trim($format) === '') {
            $format = self::DEFAULT_DATE_FORMAT;
        }

        return array_map('trim', explode('||', $format));
    }

    private function isSafeTransition(string $from, string $to): bool
    {
        if (in_array($to, self::TEXT_TYPES, true)) {
            return in_array($from, self::SCALAR_TYPES, true);
        }

        return in_array($to, self::NUMERIC_WIDENING[$from] ?? [], true);
    }

    private function shape(array $node): string
    {
        $type = $node['type'] ?? null;
        if (is_string($type) && $type !== self::SHAPE_OBJECT) {
            return $type;
        }

        // Objects are returned without a type by the engine and may carry an explicit one in GDI mappings.
        return self::SHAPE_OBJECT;
    }

    private function isContainer(string $shape): bool
    {
        return $shape === self::SHAPE_OBJECT || $shape === self::SHAPE_NESTED;
    }
}
