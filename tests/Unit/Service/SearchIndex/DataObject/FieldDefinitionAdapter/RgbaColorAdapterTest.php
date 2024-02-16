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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter\RgbaColorAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class RgbaColorAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new RgbaColorAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $mapping = $adapter->getOpenSearchMapping();
        $this->assertSame([
            'properties' => [
                'r' => [
                    'type' => 'integer',
                ],
                'g' => [
                    'type' => 'integer',
                ],
                'b' => [
                    'type' => 'integer',
                ],
                'a' => [
                    'type' => 'integer',
                ],
            ],
        ], $mapping);
    }
}