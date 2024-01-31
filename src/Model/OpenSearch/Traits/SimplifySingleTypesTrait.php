<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits;

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
