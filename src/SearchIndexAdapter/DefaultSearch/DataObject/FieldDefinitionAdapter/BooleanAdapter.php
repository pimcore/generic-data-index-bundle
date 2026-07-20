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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Model\DataObject\ClassDefinition\Data\BooleanSelect;

/**
 * @internal
 */
final class BooleanAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'type' => AttributeType::BOOLEAN->value,
        ];
    }

    public function normalize(mixed $value): mixed
    {
        // Values from object getters are already hydrated booleans; only raw
        // resource values (e.g. classification store longtext) need conversion.
        if ($value === null || is_bool($value)) {
            return $value;
        }

        $fieldDefinition = $this->getFieldDefinition();
        if ($fieldDefinition instanceof BooleanSelect) {
            // booleanSelect is tri-state (1 = yes, -1 = no, null/0 = empty);
            // a plain bool cast would turn "no" (-1) into true.
            return $fieldDefinition->getDataFromResource($value);
        }

        return (bool) $value;
    }
}
