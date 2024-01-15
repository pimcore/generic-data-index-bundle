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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\DataObject;

class SelectOptionsService
{
    public static function getKeyByValue(?string $value, array $options): ?string
    {
        if($value === null) {
            return null;
        }

        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['key'];
            }
        }

        return $value;
    }
}
