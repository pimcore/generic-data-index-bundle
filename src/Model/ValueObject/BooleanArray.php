<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\ValueObject;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidValueException;

/**
 * @internal
 */
final class BooleanArray
{
    /**
     * @throws InvalidValueException
     */
    public function __construct(
        private readonly array $values,
        private readonly bool $nullAllowed = false
    ) {
        foreach($values as $value) {
            if (!is_bool($value) && !($nullAllowed && $value === null)) {
                throw new InvalidValueException(
                    sprintf(
                        'Only boolean values are allowed (%s given).',
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
