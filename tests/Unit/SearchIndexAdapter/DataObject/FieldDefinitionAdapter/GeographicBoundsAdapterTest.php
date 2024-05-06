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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter\GeographicBoundsAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class GeographicBoundsAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new GeographicBoundsAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $mapping = $adapter->getIndexMapping();

        $this->assertSame([
            'properties' => [
                'northEast' => [
                    'properties' => [
                        'latitude' => [
                            'type' => 'float',
                        ],
                        'longitude' => [
                            'type' => 'float',
                        ],
                    ],
                ],
                'southWest' => [
                    'properties' => [
                        'latitude' => [
                            'type' => 'float',
                        ],
                        'longitude' => [
                            'type' => 'float',
                        ],
                    ],
                ],
            ],
        ], $mapping);
    }
}
