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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolver;

final class CalculatedFieldsIndexModeResolverTest extends Unit
{
    public function testResolvesTheConfiguredMode(): void
    {
        $this->assertSame(CalculatedFieldsIndexMode::LIVE, (new CalculatedFieldsIndexModeResolver('live'))->getMode());
        $this->assertSame(
            CalculatedFieldsIndexMode::QUERY_STORE,
            (new CalculatedFieldsIndexModeResolver('query_store'))->getMode()
        );
    }

    public function testDefaultsToLive(): void
    {
        $this->assertSame(CalculatedFieldsIndexMode::LIVE, (new CalculatedFieldsIndexModeResolver())->getMode());
    }
}
