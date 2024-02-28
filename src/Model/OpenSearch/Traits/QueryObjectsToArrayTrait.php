<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryInterface;

trait QueryObjectsToArrayTrait
{
    public function convertQueryObjectsToArray(array $params): array
    {
        array_walk_recursive(
            $params,
            static function (&$value) {
                if ($value instanceof QueryInterface) {
                    $value = $value->toArray(true);
                }
            }
        );

        return $params;
    }
}