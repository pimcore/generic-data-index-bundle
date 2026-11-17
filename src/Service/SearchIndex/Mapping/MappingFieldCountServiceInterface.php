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
 * Counts a mapping in the unit the search engine checks against "index.mapping.total_fields.limit", so that a
 * mapping too large for the index can be refused before the engine is asked for it.
 *
 * @internal
 */
interface MappingFieldCountServiceInterface
{
    /**
     * The counted fields of a mapping-properties array: the leaves plus the object and nested nodes plus the
     * sub-fields of a multi-field. Metadata fields are not part of the count.
     *
     * @param array<string, mixed> $properties
     */
    public function countFields(array $properties): int;

    /**
     * The branch contributing the most counted fields, named by the dotted path of a mapping property at most
     * two levels deep - for a class mapping that is the field of the class definition to look at - or null for
     * a mapping that declares nothing.
     *
     * @param array<string, mixed> $properties
     *
     * @return array{path: string, fields: int}|null
     */
    public function getLargestContributor(array $properties): ?array;
}
