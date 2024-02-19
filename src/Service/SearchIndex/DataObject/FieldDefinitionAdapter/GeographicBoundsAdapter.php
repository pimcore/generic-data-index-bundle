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


namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

/**
 * @internal
 */
final class GeographicBoundsAdapter extends AbstractAdapter
{
    use HasLatitudeAnfLongitudeTrait;

    public function getOpenSearchMapping(): array
    {
        return [
            'properties' => [
                'northEast' => $this->getLatAndLongMapping(),
                'southWest' => $this->getLatAndLongMapping()
            ]
        ];
    }
}
