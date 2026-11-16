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

// Stand-ins for generated data object classes, which live in this namespace.

namespace Pimcore\Model\DataObject;

/**
 * @internal
 */
class GdiRelationSummaryProbe extends Concrete
{
    public ?Concrete $inheritanceParent = null;

    public int $getterCalls = 0;

    public function getRelated(): array
    {
        $this->getterCalls++;

        return [];
    }

    public function getNextParentForInheritance(): ?Concrete
    {
        return $this->inheritanceParent;
    }
}

/**
 * @internal
 */
final class GdiRelationSummaryProbeWithOverriddenGetter extends GdiRelationSummaryProbe
{
    public function getRelated(): array
    {
        return [];
    }
}

/**
 * @internal
 */
final class GdiRelationSummaryProbeWithOverriddenGet extends GdiRelationSummaryProbe
{
    public function get(string $fieldName, ?string $language = null): mixed
    {
        return parent::get($fieldName, $language);
    }
}

/**
 * @internal
 */
final class GdiRelationSummaryProbeWithHook extends Concrete implements PreGetValueHookInterface
{
    public function getRelated(): array
    {
        return [];
    }

    public function preGetValue(string $key): mixed
    {
        return null;
    }
}
