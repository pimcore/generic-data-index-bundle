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

    /** Numeric widening that keeps every value (old type => accepted new types). */
    private const NUMERIC_WIDENING = [
        'byte' => ['short', 'integer', 'long', 'float', 'double'],
        'short' => ['integer', 'long', 'float', 'double'],
        'integer' => ['long', 'double'],
        'long' => ['double'],
        'half_float' => ['float', 'double'],
        'float' => ['double'],
    ];

    public function diff(array $liveProperties, array $newProperties): MappingDiff
    {
        return new MappingDiff($this->compareProperties($liveProperties, $newProperties, []));
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
            return [];
        }

        $action = !$liveIsContainer && !$newIsContainer && $this->isSafeTransition($liveShape, $newShape)
            ? MappingFieldChangeAction::KEEP
            : MappingFieldChangeAction::STRIP;

        return [new MappingFieldChange($path, $liveShape, $newShape, $action)];
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
