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

/**
 * @internal
 */
final class MappingFieldCountService implements MappingFieldCountServiceInterface
{
    private const PROPERTIES = 'properties';

    private const SUB_FIELDS = 'fields';

    public function countFields(array $properties): int
    {
        $count = 0;
        foreach ($properties as $node) {
            if (is_array($node)) {
                $count += $this->countNode($node);
            }
        }

        return $count;
    }

    public function getLargestContributor(array $properties): ?array
    {
        $largest = null;
        foreach ($this->getContributors($properties) as $path => $fields) {
            if ($largest === null || $fields > $largest['fields']) {
                $largest = ['path' => (string) $path, 'fields' => $fields];
            }
        }

        return $largest;
    }

    /**
     * The counted fields per branch, one level below a container node: a class mapping is three containers
     * (system, standard and custom fields), and naming one of them tells nobody which field to look at.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<string, int>
     */
    private function getContributors(array $properties): array
    {
        $contributors = [];
        foreach ($properties as $name => $node) {
            if (!is_array($node)) {
                continue;
            }

            $children = $node[self::PROPERTIES] ?? null;
            if (is_array($children) && $children !== [] && !isset($node[self::SUB_FIELDS])) {
                foreach ($children as $childName => $childNode) {
                    if (is_array($childNode)) {
                        $contributors[$name . '.' . $childName] = $this->countNode($childNode);
                    }
                }

                continue;
            }

            $contributors[(string) $name] = $this->countNode($node);
        }

        return $contributors;
    }

    /**
     * One mapping node: itself - a leaf, an object node or a nested node alike - plus whatever it declares
     * underneath, both the properties of an object and the sub-fields of a multi-field.
     *
     * @param array<string, mixed> $node
     */
    private function countNode(array $node): int
    {
        $count = 1;

        foreach ([self::PROPERTIES, self::SUB_FIELDS] as $container) {
            $children = $node[$container] ?? null;
            if (is_array($children)) {
                $count += $this->countFields($children);
            }
        }

        return $count;
    }
}
