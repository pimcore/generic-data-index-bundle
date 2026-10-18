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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexService\IndexHandler;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex\ReindexOutcome;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\IndexIconUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * AbstractIndexHandler is not internal: subclasses written against the released API keep working.
 *
 * @internal
 */
final class AbstractIndexHandlerBcTest extends Unit
{
    public function testSubclassCallingTheParentConstructorWithFourArgumentsStillCopies(): void
    {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getMapping' => ['test_alias-odd' => ['mappings' => ['properties' => [
                'standard_fields' => ['properties' => ['price' => ['type' => 'text']]],
            ]]]],
            'reindexWithoutFields' => new ReindexOutcome(ReindexResult::SUCCESS),
        ]);
        $handler = null;

        $deprecations = $this->captureDeprecations(function () use ($search, &$handler): void {
            $handler = $this->createLegacyHandler($search);
        });
        $handler->setLogger(new NullLogger());
        $result = $handler->reindexMappingWithResult();

        $this->assertSame(['standard_fields.price'], $result->getStrippedPaths());
        $this->assertFalse($result->isRecreated());
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('IndexCopyServiceInterface', $deprecations[0]);
        $this->assertStringContainsString('is deprecated', $deprecations[0]);
    }

    public function testPassingTheCopyServiceTriggersNoDeprecation(): void
    {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class);

        $deprecations = $this->captureDeprecations(function () use ($search): void {
            $this->createHandler($search);
        });

        $this->assertSame([], $deprecations);
    }

    public function testSubclassOverridingReindexMappingWithTheReleasedSignatureLoads(): void
    {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class);
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);
        $copy = new IndexCopyService($search, new MappingDiffService(), new NullLogger());
        $handler = new class($search, $config, $events, $mapping, $copy) extends AbstractIndexHandler {
            public int $calls = 0;

            public function reindexMapping(
                ?ClassDefinition $context = null,
                ?array $mappingProperties = null
            ): void {
                $this->calls++;
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return [];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };

        $handler->reindexMapping();

        $this->assertSame(1, $handler->calls, 'The released void signature can still be overridden');
    }

    /**
     * A project that replaces a handler service with its own subclass keeps its reindexMapping() override:
     * the bundle calls it, and because its outcome is unknown, the class is queued for a rebuild.
     */
    public function testAnOverriddenReindexMappingIsCalledByTheBundleAndTriggersARebuild(): void
    {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class);
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);
        $copy = new IndexCopyService($search, new MappingDiffService(), new NullLogger());
        $handler = new class($search, $config, $events, $mapping, $copy) extends AbstractIndexHandler {
            public int $calls = 0;

            public function reindexMapping(
                ?ClassDefinition $context = null,
                ?array $mappingProperties = null
            ): void {
                $this->calls++;
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return ['standard_fields' => ['properties' => ['price' => ['type' => 'float']]]];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());
        $enqueue = null;
        $enqueue = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => Expected::once(static function () use (&$enqueue): EnqueueServiceInterface {
                return $enqueue;
            }),
        ]);
        $service = new ClassDefinitionReindexService(
            $handler,
            $enqueue,
            $this->makeEmpty(SettingsStoreServiceInterface::class, ['getClassMappingCheckSum' => 999]),
            $this->makeEmpty(IndexIconUpdateServiceInterface::class, ['updateIcon' => null]),
            $this->makeEmpty(LoggerInterface::class),
        );
        $classDefinition = new ClassDefinition();
        $classDefinition->setId('TEST');
        $classDefinition->setName('Test');
        $classDefinition->setIcon('/bundles/pimcoreadmin/img/flat-color-icons/cube.svg');

        $result = $service->reindexClassDefinitionMapping($classDefinition, true);

        $this->assertSame(1, $handler->calls, 'The override is called on a class save');
        $this->assertNotNull($result);
        $this->assertTrue($result->requiresRebuild());
        $this->assertFalse($result->isRecreated());
        $this->assertSame([], $result->getStrippedPaths());
    }

    /**
     * A subclass may keep its old reindexMapping() and let it delegate to the new method. The bundle calls the
     * override, the override calls back, and the copy runs once instead of calling the override again forever.
     */
    public function testAnOverrideDelegatingToTheNewMethodCopiesOnceWithoutRecursion(): void
    {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getMapping' => ['test_alias-odd' => ['mappings' => ['properties' => [
                'standard_fields' => ['properties' => ['price' => ['type' => 'text']]],
            ]]]],
            'reindexWithoutFields' => Expected::once(new ReindexOutcome(ReindexResult::SUCCESS)),
        ]);
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);
        $copy = new IndexCopyService($search, new MappingDiffService(), new NullLogger());

        $handler = new class($search, $config, $events, $mapping, $copy) extends AbstractIndexHandler {
            public int $calls = 0;

            public function reindexMapping(
                ?ClassDefinition $context = null,
                ?array $mappingProperties = null
            ): void {
                $this->calls++;
                $this->reindexMappingWithResult($context, $mappingProperties);
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return ['standard_fields' => ['properties' => ['price' => ['type' => 'float']]]];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());

        $result = $handler->reindexMappingWithResult();

        $this->assertSame(1, $handler->calls);
        $this->assertTrue($result->isOutcomeUnknown());
        $this->assertTrue($result->requiresRebuild());
    }

    /**
     * A subclass as written for the released version: it is constructed with the four original arguments.
     */
    private function createLegacyHandler(SearchIndexServiceInterface $search): AbstractIndexHandler
    {
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);

        return new class($search, $config, $events, $mapping) extends AbstractIndexHandler {
            protected function extractMappingProperties(mixed $context = null): array
            {
                return ['standard_fields' => ['properties' => ['price' => ['type' => 'float']]]];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
    }

    private function createHandler(SearchIndexServiceInterface $search): AbstractIndexHandler
    {
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);
        $copy = new IndexCopyService($search, new MappingDiffService(), new NullLogger());

        return new class($search, $config, $events, $mapping, $copy) extends AbstractIndexHandler {
            protected function extractMappingProperties(mixed $context = null): array
            {
                return [];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
    }

    /**
     * @return string[] messages of the deprecations triggered by $code
     */
    private function captureDeprecations(callable $code): array
    {
        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $code();
        } finally {
            restore_error_handler();
        }

        return $messages;
    }
}
