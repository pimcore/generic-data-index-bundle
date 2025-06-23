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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

/**
 * @internal
 */
final class GeographicBoundsAdapter extends AbstractAdapter
{
    use HasLatitudeAnfLongitudeTrait;

    public function getIndexMapping(): array
    {
        return [
            'properties' => [
                'northEast' => $this->getLatAndLongMapping(),
                'southWest' => $this->getLatAndLongMapping(),
            ],
        ];
    }
}
