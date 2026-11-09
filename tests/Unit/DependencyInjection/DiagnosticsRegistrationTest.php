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
use Pimcore\Contracts\Diagnostics\CheckInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * The diagnostic checks implement a contract that ships with Pimcore 2026.4; on an older core their services must
 * not be registered at all.
 *
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

    public function testChecksAreRegisteredExactlyWhenTheContractExists(): void
    {
        $container = $this->loadExtension();
        $contractExists = interface_exists(CheckInterface::class);

        foreach ($this->checkClasses() as $className) {
            $this->assertSame(
                $contractExists,
                $container->hasDefinition($className),
                $className . ' must be registered exactly when the diagnostics contract exists.'
            );
        }
    }

    public function testChecksAreTaggedForTheSearchTopic(): void
    {
        if (!interface_exists(CheckInterface::class)) {
            $this->markTestSkipped('The diagnostics contract ships with Pimcore 2026.4.');
        }

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
        if (!interface_exists(CheckInterface::class)) {
            $this->markTestSkipped('The diagnostics contract ships with Pimcore 2026.4.');
        }

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

    public function testDiagnosticsFileIsOutsideEveryImportedResource(): void
    {
        $services = Yaml::parseFile(__DIR__ . '/../../../config/services.yaml');

        foreach ($services['imports'] as $import) {
            $this->assertStringNotContainsString('diagnostics', $import['resource']);
            $this->assertDoesNotMatchRegularExpression('~^\*|^\.\./?\*~', $import['resource']);
        }
        $this->assertFileExists(__DIR__ . '/../../../config/diagnostics.yaml');
        $this->assertFileDoesNotExist(__DIR__ . '/../../../config/services/diagnostics.yaml');
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
