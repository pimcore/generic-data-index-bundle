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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\RelationSummaries;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;

/**
 * Reads the related elements of a relation field from the relation rows instead of loading every
 * related element through the getter, which the index does not need: it only stores their type and id.
 *
 * @internal
 */
interface RelationSummaryServiceInterface
{
    /**
     * Like the getter, an empty value is taken from the nearest ancestor with relations when
     * $walkParents is set. Returns null when the value can only be read through the getter: the field
     * type cannot describe its relations without loading them, the project overrides the getter or
     * hooks into it, or the value of a visited object is already in memory (e.g. unsaved changes).
     */
    public function getRelationSummaries(
        Concrete $dataObject,
        Data $fieldDefinition,
        string $key,
        bool $walkParents
    ): ?RelationSummaries;
}
