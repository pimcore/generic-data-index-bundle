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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\ClassDefinition;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\LegacyClassMappingCheckSum;

/**
 * @internal
 */
final class LegacyClassMappingCheckSumTest extends Unit
{
    public function testWorkflowPlacesAreIgnored(): void
    {
        $withoutPlaces = ['zulu' => ['type' => 'keyword'], 'system_fields' => ['properties' => []]];
        $withPlaces = $withoutPlaces;
        $withPlaces['system_fields']['properties']['workflowPlaces'] = [
            'type' => 'object',
            'properties' => ['product_workflow' => ['type' => 'keyword']],
        ];

        $this->assertSame(
            crc32(json_encode($withoutPlaces, JSON_THROW_ON_ERROR)),
            LegacyClassMappingCheckSum::calculate($withPlaces)
        );
    }

    public function testKeyOrderStillCounts(): void
    {
        // The legacy checksum is the one stored before the keys were normalized.
        $this->assertNotSame(
            LegacyClassMappingCheckSum::calculate(['b' => 1, 'a' => 2]),
            LegacyClassMappingCheckSum::calculate(['a' => 2, 'b' => 1])
        );
    }
}
