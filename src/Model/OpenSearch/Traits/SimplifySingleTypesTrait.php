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
