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
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\CopyMapping;
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
     * is dropped by the rebuild. Parameters other than the type (analyzer, sub-fields) are ignored,
     * because the copy re-analyzes every document anyway. The one exception is the format of date
     * fields: date, datetime and time fields differ only in it, and a format that does not accept
     * every old format would fail the copy.
     *
     * @param array<string, mixed> $liveProperties mapping properties of the live index as the engine returns them
     * @param array<string, mixed> $newProperties mapping properties the index is updated to
     *
     * @throws JsonException when the new mapping cannot be encoded as JSON
     */
    public function diff(array $liveProperties, array $newProperties): MappingDiff;

    /**
     * The mapping for the copy target: the new mapping, plus the live mapping of every field the new
     * mapping no longer has. Without it the engine would map such fields again from the first copied
     * document, and documents that disagree ("2026-01-01" and "abc") would fail the copy. Carried
     * definitions lose analyzer and normalizer settings, and nested fields become objects. Without
     * removed fields the new mapping is returned as given.
     *
     * @param array<string, mixed> $liveProperties mapping properties of the live index as the engine returns them
     * @param array<string, mixed> $newProperties mapping properties the index is updated to
     *
     * @throws JsonException when the new mapping cannot be encoded as JSON
     */
    public function mappingForCopy(array $liveProperties, array $newProperties): CopyMapping;
}
