<?php
/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace App;

use Pimcore\Kernel as BaseKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class Kernel extends BaseKernel
{
    /**
     * Override configureContainer to prevent MicroKernelTrait from auto-loading
     * config/services.yaml. In this CI setup, the bundle IS the project root,
     * so config/services.yaml belongs to the bundle and is already loaded by the
     * bundle extension's load() method. Loading it again here would overwrite
     * the programmatic argument assignments made by the extension
     * (e.g., $clientType, $queueSettings on SearchIndexConfigServiceInterface
     * and DispatchQueueMessagesHandler).
     *
     * We still load packages and environment-specific service files
     * (services_test.yaml) which provide test-only service overrides.
     */
    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $configDir = $this->getProjectDir() . '/{config}';

        $container->import($configDir . '/{packages}/*.{php,yaml}');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.{php,yaml}');

        // Skip loading config/services.yaml — it is the bundle's own service config
        // and is already loaded by PimcoreGenericDataIndexExtension::load().
        // Only load the environment-specific services file (e.g., services_test.yaml).
        $container->import($configDir . '/{services}_' . $this->environment . '.yaml');
    }
}
