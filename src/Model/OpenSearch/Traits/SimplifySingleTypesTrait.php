<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
