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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\DependencyInjection;

use ArrayObject;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\PimcoreGenericDataIndexExtension;
use Pimcore\Bundle\GenericDataIndexBundle\Diagnostics\IndexHealthCheck;
use Pimcore\Bundle\GenericDataIndexBundle\Diagnostics\IndexQueueBacklogCheck;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class DiagnosticsRegistrationTest extends Unit
{
    private const string CHECK_TAG = 'pimcore.diagnostics.check';

    /**
     * @return list<class-string>
     */
    private function checkClasses(): array
    {
        return [IndexQueueBacklogCheck::class, IndexHealthCheck::class];
    }

    public function testChecksAreRegistered(): void
    {
        $container = $this->loadExtension();

        foreach ($this->checkClasses() as $className) {
            $this->assertTrue($container->hasDefinition($className), $className . ' must be registered.');
        }
    }

    public function testChecksAreTaggedForTheSearchTopic(): void
    {
        $tagged = $this->loadExtension()->findTaggedServiceIds(self::CHECK_TAG);

        foreach ($this->checkClasses() as $className) {
            $this->assertSame([['topic' => 'search']], $tagged[$className] ?? null);
        }
    }

    /**
     * Compiles the checks the way a consumer of the tag keeps them: every other service and alias of the bundle is
     * replaced by a synthetic placeholder, so an argument that cannot be autowired fails here without a kernel.
     */
    public function testChecksCompileWhenTheTagIsCollected(): void
    {
        $bundleContainer = $this->loadExtension();
        $container = new ContainerBuilder();
        foreach ($bundleContainer->getDefinitions() as $id => $definition) {
            if (in_array($id, $this->checkClasses(), true)) {
                $container->setDefinition($id, $definition);

                continue;
            }
            $container->setDefinition($id, (new Definition($definition->getClass()))->setSynthetic(true));
        }
        foreach ($bundleContainer->getAliases() as $id => $alias) {
            $container->setAlias($id, (string) $alias);
        }
        // Provided by the framework bundle of the application, not by this bundle.
        $container->setDefinition(ClockInterface::class, (new Definition(ClockInterface::class))->setSynthetic(true));
        $container->setDefinition('diagnostics.consumer', (new Definition(ArrayObject::class))
            ->setPublic(true)
            ->setArguments([new TaggedIteratorArgument(self::CHECK_TAG)]));

        $container->compile();

        foreach ($this->checkClasses() as $className) {
            $this->assertTrue($container->hasDefinition($className), $className . ' must survive compilation.');
        }
        $this->assertEquals(
            new Reference('generic-data-index.search-client'),
            $container->getDefinition(IndexHealthCheck::class)->getArgument(0),
        );
    }

    private function loadExtension(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $bundleConfig = Yaml::parseFile(__DIR__ . '/../../../config/pimcore/config.yaml');
        (new PimcoreGenericDataIndexExtension())->load([$bundleConfig['pimcore_generic_data_index']], $container);

        return $container;
    }
}
