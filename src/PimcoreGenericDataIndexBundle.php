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

namespace Pimcore\Bundle\GenericDataIndexBundle;

use Pimcore\Bundle\ElasticsearchClientBundle\PimcoreElasticsearchClientBundle;
use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler\SearchModifierHandlerPass;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler\ServiceLocatorPass;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\PimcoreGenericDataIndexExtension;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\ServiceTag;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Processor\SearchBodyProcessorInterface;
use Pimcore\Bundle\OpenSearchClientBundle\PimcoreOpenSearchClientBundle;
use Pimcore\Bundle\StaticResolverBundle\PimcoreStaticResolverBundle;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Pimcore\HttpKernel\Bundle\DependentBundleInterface;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use ReflectionMethod;
use Reflector;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class PimcoreGenericDataIndexBundle extends AbstractPimcoreBundle implements DependentBundleInterface
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new PimcoreGenericDataIndexExtension();
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        $this->registerSearchModifierAttribute($container);

        $container
            ->registerForAutoconfiguration(SearchBodyProcessorInterface::class)
            ->addTag(ServiceTag::SEARCH_BODY_PROCESSOR->value);

        $container
            ->addCompilerPass(new ServiceLocatorPass())
            ->addCompilerPass(new SearchModifierHandlerPass());
    }

    public function getInstaller(): ?InstallerInterface
    {
        /** @var InstallerInterface|null */
        return $this->container->get(Installer::class);
    }

    public static function registerDependentBundles(BundleCollection $collection): void
    {
        $collection->addBundle(new PimcoreElasticsearchClientBundle());
        $collection->addBundle(new PimcoreOpenSearchClientBundle());
        $collection->addBundle(new PimcoreStaticResolverBundle());
    }

    private function registerSearchModifierAttribute(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            AsSearchModifierHandler::class,
            static function (
                ChildDefinition $definition,
                AsSearchModifierHandler $attribute,
                Reflector $reflector
            ): void {
                self::configureSearchModifierHandler($definition, $reflector);
            }
        );
    }

    private static function configureSearchModifierHandler(
        ChildDefinition $definition,
        Reflector $reflector
    ): void {
        $method = ($reflector instanceof ReflectionMethod)
            ? $reflector->getName()
            : '__invoke';

        $definition->addTag(ServiceTag::SEARCH_MODIFIER_HANDLER->value, [
            'method' => $method,
        ]);
    }
}
