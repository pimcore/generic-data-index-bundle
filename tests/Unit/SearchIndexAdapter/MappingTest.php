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
