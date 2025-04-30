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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Traits;

trait SimplifySingleTypesTrait
{
    private function simplifySingleTypes(array $queries): array
    {
        foreach ($queries as $queryType => $items) {
            if (array_is_list($items) && count($items) === 1) {
                $queries[$queryType] = reset($items);
            }
        }

        return $queries;
    }
}
