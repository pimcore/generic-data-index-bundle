<?php

namespace Pimcore\Bundle\GenericDataIndexBundle;

use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler\SearchIndexFieldDefinitionPass;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PimcoreGenericDataIndexBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container)
    {
        $container
            ->addCompilerPass(new SearchIndexFieldDefinitionPass());
    }
}
