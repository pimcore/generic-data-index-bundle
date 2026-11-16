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
use function explode;
use function implode;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\GroupConfigResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\KeyConfigResolverInterface;
use function str_contains;

/**
 * The only place the bundle turns a group or a key into a path segment. The mapping and the
 * document do not restate the rule, they ask here, so the three cannot drift apart.
 *
 * @internal
 */
final readonly class IndexSegmentService implements IndexSegmentServiceInterface
{
    private const PATH_SEPARATOR = '.';

    private const VARIANT_SEPARATOR = '_';

    public function __construct(
        private GroupConfigResolverInterface $groupConfigResolver,
        private KeyConfigResolverInterface $keyConfigResolver,
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
}
