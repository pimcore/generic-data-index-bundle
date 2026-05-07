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

use Pimcore\Config\BundleConfigLocator;
use Pimcore\Kernel as BaseKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends BaseKernel
{
    /**
     * Override registerContainerConfiguration() to prevent MicroKernelTrait from
     * auto-loading config/services.yaml. In this CI setup, the bundle IS the project
     * root, so config/services.yaml belongs to the bundle and is already loaded by
     * the bundle extension's load() method. Loading it again here would overwrite
     * the programmatic argument assignments made by the extension
     * (e.g., $clientType, $queueSettings on SearchIndexConfigServiceInterface
     * and DispatchQueueMessagesHandler).
     *
     * Previously this was achieved by overriding the protected configureContainer()
     * extension point exposed by Pimcore\Kernel via a MicroKernelTrait alias.
     * That extension point relies on a private method of Symfony's MicroKernelTrait
     * whose signature is not part of Symfony's public API and changes between
     * minor versions. Overriding the public registerContainerConfiguration() method
     * instead avoids this coupling and remains stable across Symfony 6.4, 7.x and
     * future versions.
     *
     * This implementation mirrors Pimcore\Kernel::registerContainerConfiguration()
     * (BundleConfigLocator + packages + env services) but intentionally skips the
     * MicroKernelTrait services.yaml auto-load and the dynamic Pimcore config
     * directories (image_thumbnails, document_types, etc.) which are not used in
     * this bundle's CI.
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // Register the synthetic "kernel" service. This is normally done by
        // MicroKernelTrait::registerContainerConfiguration() and is required so
        // that other services may depend on the kernel via DI.
        $loader->load(function (ContainerBuilder $container): void {
            if (!$container->hasDefinition('kernel')) {
                $container->register('kernel', static::class)
                    ->addTag('controller.service_arguments')
                    ->setAutoconfigured(true)
                    ->setSynthetic(true)
                    ->setPublic(true);
            }
        });

        // Load bundle-provided config files from <bundle>/Resources/config/pimcore
        // or <bundle>/config/pimcore (e.g. config.yaml -> messenger.yaml).
        // This mirrors Pimcore\Kernel::registerContainerConfiguration().
        $bundleConfigLocator = new BundleConfigLocator($this);
        foreach ($bundleConfigLocator->locate('config') as $bundleConfig) {
            $loader->load($bundleConfig);
        }

        $configDir = $this->getProjectDir() . '/config';

        // Load packages (mirrors MicroKernelTrait's default configureContainer behavior).
        $loader->load($configDir . '/packages/*.{php,yaml}', 'glob');
        $loader->load($configDir . '/packages/' . $this->environment . '/*.{php,yaml}', 'glob');

        // Skip loading config/services.yaml — it is the bundle's own service config
        // and is already loaded by PimcoreGenericDataIndexExtension::load().
        // Only load the environment-specific services file (e.g., services_test.yaml).
        $envServices = $configDir . '/services_' . $this->environment . '.yaml';
        if (file_exists($envServices)) {
            $loader->load($envServices);
        }
    }
}
