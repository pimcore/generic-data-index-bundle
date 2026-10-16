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

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;

/**
 * Compares the mapping of a live index with the mapping it is updated to.
 *
 * @internal
 */
interface MappingDiffServiceInterface
{
    /**
     * Fields that exist in both mappings with a different type or shape. Fields that were
     * added or removed are not changes: an added field is empty in the copy, a removed field
     * is dropped by the rebuild. Parameters other than the type (analyzer, sub-fields, format)
     * are ignored, because the copy re-analyzes every document anyway.
     *
     * @param array<string, mixed> $liveProperties mapping properties of the live index as the engine returns them
     * @param array<string, mixed> $newProperties mapping properties the index is updated to
     */
    public function diff(array $liveProperties, array $newProperties): MappingDiff;
}
