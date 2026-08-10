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
    public function testDefaultsToConfiguredMode(): void
    {
        $this->assertSame(CalculatedFieldsIndexMode::LIVE, (new CalculatedFieldsIndexModeResolver('live'))->getMode());
        $this->assertSame(
            CalculatedFieldsIndexMode::QUERY_STORE,
            (new CalculatedFieldsIndexModeResolver('query_store'))->getMode()
        );
    }

    public function testNoOverrideByDefault(): void
    {
        $this->assertNull((new CalculatedFieldsIndexModeResolver('query_store'))->getOverrideMode());
    }

    public function testOverrideWinsOverConfiguredModeAndIsReadable(): void
    {
        $resolver = new CalculatedFieldsIndexModeResolver('query_store');

        $resolver->overrideMode(CalculatedFieldsIndexMode::LIVE);
        $this->assertSame(CalculatedFieldsIndexMode::LIVE, $resolver->getMode());
        $this->assertSame(CalculatedFieldsIndexMode::LIVE, $resolver->getOverrideMode());

        $resolver->overrideMode(null);
        $this->assertSame(CalculatedFieldsIndexMode::QUERY_STORE, $resolver->getMode());
        $this->assertNull($resolver->getOverrideMode());
    }
}
