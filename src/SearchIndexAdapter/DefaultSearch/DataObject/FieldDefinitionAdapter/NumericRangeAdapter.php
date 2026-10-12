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

use Pimcore\Model\DataObject\ClassDefinition\Data\NumericRange;

/**
 * @internal
 */
final class NumericRangeAdapter extends AbstractAdapter
{
    use NumericMappingTrait;

    public function getIndexMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        $numericMapping = $this->getNumericMapping(
            $fieldDefinition instanceof NumericRange && $fieldDefinition->getInteger()
        );

        return [
            'properties' => [
                'maximum' => $numericMapping,
                'minimum' => $numericMapping,
            ],
        ];
    }
}
