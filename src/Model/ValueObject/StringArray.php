<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\ValueObject;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidValueException;

/**
 * @internal
 */
final class StringArray
{
    /**
     * @throws InvalidValueException
     */
    public function __construct(
        private readonly array $values,
        private readonly bool $nullAllowed = false
    ) {
        foreach($values as $value) {
            if (!is_string($value) && !($nullAllowed && $value === null)) {
                throw new InvalidValueException(
                    sprintf(
                        'Only string values are allowed (%s given).',
                        gettype($value)
                    )
                );
            }
        }
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function isNullAllowed(): bool
    {
        return $this->nullAllowed;
    }
}