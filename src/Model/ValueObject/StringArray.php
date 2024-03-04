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
final class StringArray
{
    /**
     * @throws InvalidValueException
     */
    public function __construct(
        private readonly array $values,
    ) {
        foreach($values as $value) {
            if (!is_string($value)) {
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
}
