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

namespace Pimcore\Bundle\GenericDataIndexBundle;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler\SearchIndexFieldDefinitionPass;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler\SearchModifierHandlerPass;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\CompilerPassTag;
use Pimcore\Bundle\OpenSearchClientBundle\PimcoreOpenSearchClientBundle;
use Pimcore\Bundle\StaticResolverBundle\PimcoreStaticResolverBundle;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Pimcore\HttpKernel\Bundle\DependentBundleInterface;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use ReflectionMethod;
use Reflector;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PimcoreGenericDataIndexBundle extends AbstractPimcoreBundle implements
    PimcoreBundleAdminClassicInterface,
    DependentBundleInterface
{
    use BundleAdminClassicTrait;

    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        $this->registerSearchModifierAttribute($container);

        $container
            ->addCompilerPass(new SearchIndexFieldDefinitionPass())
            ->addCompilerPass(new SearchModifierHandlerPass());
    }

    public function getInstaller(): ?InstallerInterface
    {
        /** @var InstallerInterface|null */
        return $this->container->get(Installer::class);
    }

    public static function registerDependentBundles(BundleCollection $collection): void
    {
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

                $method = ($reflector instanceof ReflectionMethod)
                    ? $reflector->getName()
                    : '__invoke';

                $definition->addTag(CompilerPassTag::SEARCH_MODIFIER_HANDLER->value, [
                    'method' => $method,
                ]);
            }
        );
    }
}
