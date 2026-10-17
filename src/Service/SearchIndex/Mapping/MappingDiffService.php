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

    /** The format the engine applies to date fields without an explicit one. */
    private const DEFAULT_DATE_FORMAT = 'strict_date_optional_time||epoch_millis';

    /**
     * Numeric widening that keeps every value (old type => accepted new types).
     *
     * float to long: before 2026.2.8 the bundle mapped every numeric field as float; since then integer fields are
     * mapped as long. A float in a live index therefore comes from that version, and for an integer field it holds
     * whole numbers, which the engine copies into long unchanged. A field switched from decimal to integer later is
     * double, and double to long stays stripped.
     */
    private const NUMERIC_WIDENING = [
        'byte' => ['short', 'integer', 'long', 'float', 'double'],
        'short' => ['integer', 'long', 'float', 'double'],
        'integer' => ['long', 'double'],
        'long' => ['double'],
        'half_float' => ['float', 'double'],
        'float' => ['double', 'long'],
    ];

    /**
     * @throws JsonException
     */
    public function diff(array $liveProperties, array $newProperties): MappingDiff
    {
        $newProperties = $this->asSentToTheEngine($newProperties);

        return new MappingDiff(
            $this->compareProperties($liveProperties, $newProperties, []),
            $this->findRemoved($liveProperties, $newProperties, [])
        );
    }

    /**
     * @throws JsonException
     */
    public function resolveSourceFields(array $newProperties, array $paths): array
    {
        $expanded = $this->asSentToTheEngine($newProperties);
        $resolved = [];
        foreach ($paths as $segments) {
            $field = $this->resolveSourceField($expanded, $segments);
            $resolved[implode('.', $field)] = $field;
        }

        return array_values($resolved);
    }

    /**
     * @param array<string, mixed> $expanded the new mapping, expanded like the engine does
     * @param string[] $segments
     *
     * @return string[]
     */
    private function resolveSourceField(array $expanded, array $segments): array
    {
        $children = $expanded;
        $node = null;
        foreach ($segments as $position => $segment) {
            if (is_array($children[$segment] ?? null)) {
                $node = $children[$segment];
                $children = is_array($node['properties'] ?? null) ? $node['properties'] : [];

                continue;
            }

            // A sub-field of the field reached so far: the document holds that field.
            return $position > 0 && is_array($node['fields'][$segment] ?? null)
                ? array_slice($segments, 0, $position)
                : $segments;
        }

        return $segments;
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
            $this->insertAt($expanded, explode('.', (string) $name), $node);
        }

        return $expanded;
    }

    /**
     * Inserts in place: the map grows with every property, copying it per insert would be quadratic.
     *
     * @param string[] $segments
     */
    private function insertAt(array &$properties, array $segments, mixed $node): void
    {
        $name = (string) array_shift($segments);
        if ($segments === []) {
            $existing = $properties[$name] ?? null;
            $properties[$name] = is_array($existing) && is_array($node)
                ? array_replace_recursive($existing, $node)
                : $node;

            return;
        }

        if (!is_array($properties[$name] ?? null)) {
            $properties[$name] = [];
        }
        if (!is_array($properties[$name]['properties'] ?? null)) {
            $properties[$name]['properties'] = [];
        }
        $this->insertAt($properties[$name]['properties'], $segments, $node);
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
     * Live fields the new mapping no longer has; within objects present on both sides per field.
     *
     * @param string[] $parentPath
     *
     * @return string[][]
     */
    private function findRemoved(array $live, array $new, array $parentPath): array
    {
        $removed = [];
        foreach ($live as $name => $liveNode) {
            if (!is_array($liveNode)) {
                continue;
            }
            $path = [...$parentPath, (string) $name];
            $newNode = $new[$name] ?? null;
            if (!is_array($newNode)) {
                $removed[] = $path;

                continue;
            }
            if ($this->isContainer($this->shape($liveNode)) && $this->isContainer($this->shape($newNode))) {
                $liveChildren = is_array($liveNode['properties'] ?? null) ? $liveNode['properties'] : [];
                $newChildren = is_array($newNode['properties'] ?? null) ? $newNode['properties'] : [];
                array_push($removed, ...$this->findRemoved($liveChildren, $newChildren, $path));
            }
        }

        return $removed;
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
