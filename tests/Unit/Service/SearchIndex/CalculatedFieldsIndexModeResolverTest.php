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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Psr\Log\NullLogger;

final class CalculatedFieldsIndexModeResolverTest extends Unit
{
    protected function _after(): void
    {
        putenv(CalculatedFieldsIndexModeResolverInterface::ENV_VAR);
    }

    public function testDefaultsToConfiguredMode(): void
    {
        $this->assertSame(CalculatedFieldsIndexMode::LIVE, $this->createResolver('live')->getMode());
        $this->assertSame(CalculatedFieldsIndexMode::QUERY_STORE, $this->createResolver('query_store')->getMode());
    }

    public function testEnvironmentVariableOverridesConfiguredMode(): void
    {
        putenv(CalculatedFieldsIndexModeResolverInterface::ENV_VAR . '=live');

        $this->assertSame(CalculatedFieldsIndexMode::LIVE, $this->createResolver('query_store')->getMode());
    }

    public function testInvalidEnvironmentValueFallsBackToConfiguredMode(): void
    {
        putenv(CalculatedFieldsIndexModeResolverInterface::ENV_VAR . '=nonsense');

        $this->assertSame(CalculatedFieldsIndexMode::QUERY_STORE, $this->createResolver('query_store')->getMode());
    }

    public function testProcessOverrideWinsOverEnvironmentAndConfig(): void
    {
        putenv(CalculatedFieldsIndexModeResolverInterface::ENV_VAR . '=query_store');

        $resolver = $this->createResolver('query_store');
        $resolver->overrideMode(CalculatedFieldsIndexMode::LIVE);

        $this->assertSame(CalculatedFieldsIndexMode::LIVE, $resolver->getMode());

        $resolver->overrideMode(null);
        $this->assertSame(CalculatedFieldsIndexMode::QUERY_STORE, $resolver->getMode());
    }

    private function createResolver(string $configuredMode): CalculatedFieldsIndexModeResolver
    {
        $resolver = new CalculatedFieldsIndexModeResolver($configuredMode);
        $resolver->setLogger(new NullLogger());

        return $resolver;
    }
}
