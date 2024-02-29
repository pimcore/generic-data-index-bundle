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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Mapping;

/**
 * @internal
 */
final class MappingTest extends Unit
{
    public function testGetMappingName(): void
    {
        $mapping = new Mapping('testName', ['testKey' => 'testValue']);
        $this->assertSame('testName', $mapping->getMappingName());
    }

    public function testGetMapping(): void
    {
        $mapping = new Mapping('testName', ['testKey' => 'testValue']);
        $this->assertSame(['testKey' => 'testValue'], $mapping->getMapping());
    }
}
