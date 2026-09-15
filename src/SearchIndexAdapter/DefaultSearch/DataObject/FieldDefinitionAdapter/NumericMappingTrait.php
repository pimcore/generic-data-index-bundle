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

/**
 * Integer fields are stored as 64-bit long and everything else as 64-bit double, so
 * large identifiers or high-precision decimals are not collapsed by float32 rounding.
 *
 * @internal
 */
trait NumericMappingTrait
{
    private function getNumericMapping(bool $integer): array
    {
        return [
            'type' => $integer ? AttributeType::LONG->value : AttributeType::DOUBLE->value,
        ];
    }
}
