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

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\AsSubQueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryInterface;

trait QueryObjectsToArrayTrait
{
    private function convertQueryObjectsToArray(array $params): array
    {
        array_walk_recursive(
            $params,
            static function (&$value) {
                if ($value instanceof AsSubQueryInterface) {
                    $value = $value->toArrayAsSubQuery();
                } elseif ($value instanceof QueryInterface) {
                    $value = $value->toArray(true);
                }
            }
        );

        return $params;
    }
}
