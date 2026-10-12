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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\MessageHandler;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateClassMappingMessage;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\UpdateClassMappingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final class UpdateClassMappingHandlerTest extends Unit
{
    public function testReindexesFreshlyLoadedClassDefinition(): void
    {
        $staleDefinition = (new ClassDefinition())->setId('car');
        $freshDefinition = (new ClassDefinition())->setId('car');

        $handler = new UpdateClassMappingHandler(
            $this->makeEmpty(EnqueueServiceInterface::class, [
                'dispatchQueueMessages' => Expected::once(),
            ]),
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
                'reindexClassDefinition' => Expected::once(
                    function (ClassDefinition $classDefinition) use ($freshDefinition): bool {
                        $this->assertSame($freshDefinition, $classDefinition);

                        return true;
                    }
                ),
            ]),
            $this->makeEmpty(ClassDefinitionResolverInterface::class, [
                'getById' => Expected::once(function (string $id, bool $force) use ($freshDefinition) {
                    $this->assertSame('car', $id);
                    $this->assertTrue($force);

                    return $freshDefinition;
                }),
            ])
        );

        $handler(new UpdateClassMappingMessage($staleDefinition, true));
    }

    public function testSkipsDeletedClassDefinition(): void
    {
        $handler = new UpdateClassMappingHandler(
            $this->makeEmpty(EnqueueServiceInterface::class, [
                'dispatchQueueMessages' => Expected::never(),
            ]),
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
                'reindexClassDefinition' => Expected::never(),
            ]),
            $this->makeEmpty(ClassDefinitionResolverInterface::class, [
                'getById' => null,
            ])
        );

        $handler(new UpdateClassMappingMessage((new ClassDefinition())->setId('car'), true));
    }
}
