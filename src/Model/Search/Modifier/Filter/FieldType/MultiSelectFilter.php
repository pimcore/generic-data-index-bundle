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

final readonly class MultiSelectFilter implements SearchModifierInterface
{
    public function __construct(
        private string $field,
        private array $values,
        private bool $enablePqlFieldNameResolution = true,
        /**
         * When true, '' also matches elements where the field has no value (top-level fields only,
         * nested filters such as ClassificationStoreFilter keep exact matching).
         */
        private bool $matchMissingForEmptyValue = false,
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

    /**
     * Whether an empty string value also matches documents where the field is missing (null).
     */
    public function isMatchMissingForEmptyValueEnabled(): bool
    {
        return $this->matchMissingForEmptyValue;
    }

    private function validate(): void
    {
        foreach ($this->values as $value) {
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new ValueError(
                    sprintf(
                        'Provided array must contain only string, int or float values. (%s given)',
                        gettype($value)
                    ),
                );
            }
        }
    }
}
