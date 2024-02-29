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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\AsSubQueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryInterface;

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
