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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\DataObject;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\DataObject\SelectOptionsService;

class SelectOptionsServiceTest extends Unit
{
    public function testGetKeyByValue(): void
    {
        $options = [
            [
                'key' => 'key1',
                'value' => 'value1',
            ],
            [
                'key' => 'key2',
                'value' => 'value2',
            ],
        ];

        $service = new SelectOptionsService();
        $key = SelectOptionsService::getKeyByValue('value1', $options);
        static::assertEquals('key1', $key);
    }
}
