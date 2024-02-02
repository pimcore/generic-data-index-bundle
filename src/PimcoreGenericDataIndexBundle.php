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

use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler\SearchIndexFieldDefinitionPass;
use Pimcore\Bundle\OpenSearchClientBundle\PimcoreOpenSearchClientBundle;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Pimcore\HttpKernel\Bundle\DependentBundleInterface;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
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
        $container
            ->addCompilerPass(new SearchIndexFieldDefinitionPass());
    }

    public function getInstaller(): ?InstallerInterface
    {
        /** @var InstallerInterface|null */
        return $this->container->get(Installer::class);
    }

    public static function registerDependentBundles(BundleCollection $collection): void
    {
        $collection->addBundle(new PimcoreOpenSearchClientBundle());
    }
}
