<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */


namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Validater;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;

/**
 * @internal
 */
trait HasPositiveIntArrayTrait
{
    private function validatePositiveIntArray(array $array): void
    {
        foreach ($array as $id) {
            if (!is_int($id)) {
                throw new InvalidModifierException("Array must contain only integers.");
            }

            if ($id <= 0) {
                throw new InvalidModifierException("Value must be a positive integer.");
            }
        }
    }
}
