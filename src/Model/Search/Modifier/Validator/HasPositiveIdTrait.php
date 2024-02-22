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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Validator;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;

/**
 * @internal
 */
trait HasPositiveIdTrait
{
    private function validatePositiveInt(int $id): void
    {
        if ($id < 1) {
            throw new InvalidModifierException('ID must be a positive integer.');
        }
    }
}
