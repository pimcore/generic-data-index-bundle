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
