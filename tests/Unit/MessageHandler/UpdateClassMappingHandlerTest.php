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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexTimeoutException;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateClassMappingMessage;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\UpdateClassMappingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Messenger\MappingUpdateRetryPolicy;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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
            ]),
            new MappingUpdateRetryPolicy()
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
            ]),
            new MappingUpdateRetryPolicy()
        );

        $handler(new UpdateClassMappingMessage((new ClassDefinition())->setId('car'), true));
    }

    public function testACopyThatRanOutOfItsBudgetIsNotRetried(): void
    {
        $handler = new UpdateClassMappingHandler(
            $this->makeEmpty(EnqueueServiceInterface::class, ['dispatchQueueMessages' => Expected::never()]),
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
                'reindexClassDefinition' => Expected::once(static function (): never {
                    throw new ClassDefinitionIndexUpdateFailedException(
                        message: 'did not complete within 3600 seconds',
                        previous: new ReindexTimeoutException('Reindex task 1 did not complete within 3600 seconds')
                    );
                }),
            ]),
            $this->makeEmpty(ClassDefinitionResolverInterface::class, [
                'getById' => (new ClassDefinition())->setId('car'),
            ]),
            new MappingUpdateRetryPolicy()
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new UpdateClassMappingMessage((new ClassDefinition())->setId('car'), true));
    }

    public function testEveryOtherFailureIsRetriedByMessenger(): void
    {
        $failure = new ClassDefinitionIndexUpdateFailedException(
            message: 'cURL error 7: connection refused',
            previous: new RuntimeException('cURL error 7: connection refused')
        );
        $handler = new UpdateClassMappingHandler(
            $this->makeEmpty(EnqueueServiceInterface::class, ['dispatchQueueMessages' => Expected::never()]),
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
                'reindexClassDefinition' => Expected::once(static function () use ($failure): never {
                    throw $failure;
                }),
            ]),
            $this->makeEmpty(ClassDefinitionResolverInterface::class, [
                'getById' => (new ClassDefinition())->setId('car'),
            ]),
            new MappingUpdateRetryPolicy()
        );

        try {
            $handler(new UpdateClassMappingMessage((new ClassDefinition())->setId('car'), true));
        } catch (ClassDefinitionIndexUpdateFailedException $e) {
            $this->assertSame($failure, $e);

            return;
        }

        $this->fail('A transient failure reaches Messenger unchanged, which retries the message');
    }
}
