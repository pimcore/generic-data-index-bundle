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

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\PimcoreGenericDataIndexExtension;
use Pimcore\Bundle\GenericDataIndexBundle\Diagnostics\IndexQueueBacklogCheck;
use Pimcore\Contracts\Diagnostics\CheckInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
        return [IndexQueueBacklogCheck::class];
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
