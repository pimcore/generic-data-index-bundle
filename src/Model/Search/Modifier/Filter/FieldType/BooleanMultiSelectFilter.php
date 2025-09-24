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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use ValueError;

final readonly class BooleanMultiSelectFilter implements SearchModifierInterface
{
    public function __construct(
        private string $field,
        private array $values,
        private bool $enablePqlFieldNameResolution = true,
    ) {
        $this->validate();
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function isPqlFieldNameResolutionEnabled(): bool
    {
        return $this->enablePqlFieldNameResolution;
    }

    private function validate(): void
    {
        foreach ($this->values as $value) {
            if (!is_bool($value) && !is_null($value)) {
                throw new ValueError(
                    sprintf(
                        'Provided array must contain only boolean or null values. (%s given)',
                        gettype($value)
                    ),
                );
            }
        }
    }
}
