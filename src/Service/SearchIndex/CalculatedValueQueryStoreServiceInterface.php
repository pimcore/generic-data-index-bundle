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

use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;
use Pimcore\Model\DataObject\Concrete;

/**
 * Reads calculated field values from the object's query table (`object_query_*`,
 * written on save) instead of executing the calculator.
 *
 * The stored value is the save-time snapshot as a string, truncated to the field's
 * `columnLength` — the same value SQL-based grid listings use. Returns null when no
 * row or column exists (e.g. the element was never saved since the field was added).
 *
 * @internal
 */
interface CalculatedValueQueryStoreServiceInterface
{
    public function getValue(Concrete $dataObject, CalculatedValue $fieldDefinition): ?string;

    public function getLocalizedValue(
        Concrete $dataObject,
        CalculatedValue $fieldDefinition,
        string $language
    ): ?string;
}
