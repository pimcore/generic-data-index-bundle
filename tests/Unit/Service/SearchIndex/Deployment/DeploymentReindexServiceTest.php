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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Deployment;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingFieldChangeAction;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexState;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingFieldChange;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\DeploymentReindexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final class DeploymentReindexServiceTest extends Unit
{
    private const CURRENT = ['mapping-current', 'settings-current', '1.0'];

    /**
     * @dataProvider providePlans
     *
     * @param array{0: ?string, 1: ?string, 2: ?string} $stored
     */
    public function testPlanForAssetIndex(
        array $stored,
        bool $indexExists,
        string $expectedAction,
        bool $expectedNativeReindex,
        bool $expectedRebuild,
        string $expectedReason
    ): void {
        $service = $this->createService($this->createStateService($stored, $indexExists));

        $plan = $service->plan(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS));

        $this->assertSame($expectedAction, $plan->getActionLabel());
        $this->assertSame($expectedNativeReindex, $plan->needsNativeReindex());
        $this->assertSame($expectedRebuild, $plan->needsRebuild());
        $this->assertStringContainsString($expectedReason, implode(', ', $plan->getReasons()) ?: '-');
    }

    public static function providePlans(): array
    {
        return [
            'nothing changed' => [self::CURRENT, true, 'skip', false, false, '-'],
            'nothing stored yet' => [[null, null, null], true, 'store baseline', false, false, 'no stored state yet'],
            'only the settings hash missing' => [
                ['mapping-current', null, '1.0'], true, 'store baseline', false, false, 'no stored state yet',
            ],
            'mapping changed' => [
                ['mapping-old', 'settings-current', '1.0'],
                true,
                'native reindex, rebuild from database',
                true,
                true,
                'mapping changed',
            ],
            'settings changed' => [
                ['mapping-current', 'settings-old', '1.0'], true, 'native reindex', true, false, 'index settings changed',
            ],
            'data version changed' => [
                ['mapping-current', 'settings-current', '0.0'],
                true,
                'rebuild from database',
                false,
                true,
                'data version changed (0.0 -> 1.0)',
            ],
            'settings and data version changed' => [
                ['mapping-current', 'settings-old', '0.0'],
                true,
                'native reindex, rebuild from database',
                true,
                true,
                'index settings changed',
            ],
            'unfinished snapshot import' => [
                [IndexStateServiceInterface::INVALIDATED, 'settings-current', '1.0'],
                true,
                'native reindex, rebuild from database',
                true,
                true,
                'previous snapshot import did not finish',
            ],
            'index missing' => [self::CURRENT, false, 'create index, rebuild from database', false, true, 'index missing'],
            'index missing before the first run' => [
                [null, null, null], false, 'create index, rebuild from database', false, true, 'index missing',
            ],
        ];
    }

    public function testSettingsChangeReindexesWithoutRebuildAndStoresTheState(): void
    {
        $stored = [];
        $stateService = $this->createStateService(
            ['mapping-current', 'settings-old', '1.0'],
            true,
            static function (IndexStateTarget $target, IndexState $state) use (&$stored): void {
                $stored[] = [$target->getKey(), $state->getMappingChecksum(), $state->getSettingsHash()];
            }
        );
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'reindexMappingWithResult' => Expected::once(new MappingUpdateResult()),
            'updateMapping' => Expected::never(),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueDocuments' => Expected::never(),
        ]);
        $service = $this->createService($stateService, enqueueService: $enqueueService, handler: $handler);

        $enqueued = $service->execute($service->plan(IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS)));

        $this->assertFalse($enqueued);
        $this->assertSame([['document', 'mapping-current', 'settings-current']], $stored);
    }

    public function testMissingIndexIsCreatedAndRebuilt(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'updateMapping' => Expected::once(),
            'reindexMappingWithResult' => Expected::never(),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueDataObjectFolders' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
        ]);
        $service = $this->createService(
            $this->createStateService(self::CURRENT, false),
            enqueueService: $enqueueService,
            handler: $handler
        );

        $this->assertTrue(
            $service->execute($service->plan(IndexStateTarget::forScope(DeploymentReindexScope::FOLDERS)))
        );
    }

    public function testBaselineStoresTheStateWithoutTouchingTheIndex(): void
    {
        $storeCalls = 0;
        $stateService = $this->createStateService(
            [null, null, null],
            true,
            static function () use (&$storeCalls): void {
                $storeCalls++;
            }
        );
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'updateMapping' => Expected::never(),
            'reindexMappingWithResult' => Expected::never(),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueAssets' => Expected::never(),
        ]);
        $service = $this->createService($stateService, enqueueService: $enqueueService, handler: $handler);

        $this->assertFalse(
            $service->execute($service->plan(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS)))
        );
        $this->assertSame(1, $storeCalls);
    }

    public function testUnchangedIndexStoresNothing(): void
    {
        $stateService = $this->createStateService(self::CURRENT, true, Expected::never());
        $service = $this->createService($stateService);

        $this->assertFalse(
            $service->execute($service->plan(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS)))
        );
    }

    public function testFailedReindexDoesNotStoreTheState(): void
    {
        $stateService = $this->createStateService(['mapping-old', 'settings-current', '1.0'], true, Expected::never());
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'reindexMappingWithResult' => static function (): MappingUpdateResult {
                throw new Exception('reindex failed (simulated)');
            },
        ]);
        $service = $this->createService($stateService, handler: $handler);
        $plan = $service->plan(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS));

        $this->expectExceptionMessage('reindex failed (simulated)');
        $service->execute($plan);
    }

    public function testUnchangedClassStillRunsTheClassDefinitionReindex(): void
    {
        $classDefinitionReindexService = $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
            'isMappingChanged' => false,
            'reindexClassDefinition' => Expected::once(false),
        ]);
        $service = $this->createService(
            $this->createStateService(self::CURRENT, true, Expected::never()),
            $classDefinitionReindexService
        );

        $plan = $service->plan(IndexStateTarget::forClassDefinition($this->createClassDefinition()));

        $this->assertTrue($plan->isSkipped());
        $this->assertFalse($service->execute($plan));
    }

    public function testClassWithoutStoredChecksumIsReindexedLikeWithoutOptions(): void
    {
        $classDefinitionReindexService = $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
            'isMappingChanged' => true,
            'reindexClassDefinition' => Expected::once(
                static function (ClassDefinition $classDefinition, bool $skipIfClassNotChanged, bool $enqueue): bool {
                    self::assertTrue($skipIfClassNotChanged);
                    self::assertTrue($enqueue);

                    return true;
                }
            ),
        ]);
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'reindexMappingWithResult' => Expected::never(),
        ]);
        $service = $this->createService(
            $this->createStateService([null, 'settings-current', '1.0'], true),
            $classDefinitionReindexService,
            handler: $handler
        );

        $plan = $service->plan(IndexStateTarget::forClassDefinition($this->createClassDefinition()));

        $this->assertSame('native reindex, rebuild from database', $plan->getActionLabel());
        $this->assertTrue($service->execute($plan));
    }

    public function testClassSettingsChangeReindexesTheClassIndexWithoutRebuild(): void
    {
        $classDefinition = $this->createClassDefinition();
        $classDefinitionReindexService = $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
            'isMappingChanged' => false,
            'reindexClassDefinition' => Expected::once(false),
        ]);
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'reindexMappingWithResult' => Expected::once(
                static function (?ClassDefinition $context) use ($classDefinition): MappingUpdateResult {
                    self::assertSame($classDefinition, $context);

                    return new MappingUpdateResult();
                }
            ),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => Expected::never(),
        ]);
        $service = $this->createService(
            $this->createStateService(['ignored-for-classes', 'settings-old', '1.0'], true),
            $classDefinitionReindexService,
            $enqueueService,
            $handler
        );

        $this->assertFalse($service->execute($service->plan(IndexStateTarget::forClassDefinition($classDefinition))));
    }

    /**
     * The class mapping is current, but the settings copy still left out fields (values written under an older
     * mapping): only the rebuild restores them, so the objects are queued.
     */
    public function testClassSettingsCopyThatStripsFieldsQueuesTheObjects(): void
    {
        $classDefinition = $this->createClassDefinition();
        $classDefinitionReindexService = $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
            'isMappingChanged' => false,
            'reindexClassDefinition' => Expected::once(false),
        ]);
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'reindexMappingWithResult' => Expected::once(new MappingUpdateResult(['standard_fields.price'])),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
        ]);
        $service = $this->createService(
            $this->createStateService(['ignored-for-classes', 'settings-old', '1.0'], true),
            $classDefinitionReindexService,
            $enqueueService,
            $handler
        );

        $this->assertTrue($service->execute($service->plan(IndexStateTarget::forClassDefinition($classDefinition))));
    }

    public function testClassDataVersionChangeRebuildsTheClass(): void
    {
        $classDefinitionReindexService = $this->makeEmpty(ClassDefinitionReindexServiceInterface::class, [
            'isMappingChanged' => false,
            'reindexClassDefinition' => Expected::once(false),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueByClassDefinition' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
        ]);
        $service = $this->createService(
            $this->createStateService(['ignored-for-classes', 'settings-current', '0.0'], true),
            $classDefinitionReindexService,
            $enqueueService
        );

        $this->assertTrue(
            $service->execute($service->plan(IndexStateTarget::forClassDefinition($this->createClassDefinition())))
        );
    }

    public function testPlanNamesTheFieldsTheCopyStripsAndKeeps(): void
    {
        $diff = new MappingDiff([
            new MappingFieldChange(['standard_fields', 'price'], 'text', 'float', MappingFieldChangeAction::STRIP),
            new MappingFieldChange(['standard_fields', 'count'], 'long', 'text', MappingFieldChangeAction::KEEP),
            new MappingFieldChange(['system_fields', 'id'], 'long', 'integer', MappingFieldChangeAction::PROTECT),
        ]);
        $handler = $this->makeEmpty(IndexHandlerInterface::class, ['getMappingDiff' => $diff]);
        $service = $this->createService(
            $this->createStateService(['mapping-old', 'settings-current', '1.0'], true),
            handler: $handler
        );

        $reasons = $service->plan(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS))->getReasons();

        $this->assertContains('fields stripped in the copy: standard_fields.price', $reasons);
        $this->assertContains('fields kept in the copy: standard_fields.count', $reasons);
        $this->assertContains(
            'fields outside class and custom fields copied as they are, '
            . 'the index is recreated if their values do not fit: system_fields.id',
            $reasons
        );
    }

    public function testSettingsOnlyCopyThatStrippedFieldsQueuesARebuild(): void
    {
        $handler = $this->makeEmpty(IndexHandlerInterface::class, [
            'getMappingDiff' => new MappingDiff([]),
            'reindexMappingWithResult' => Expected::once(new MappingUpdateResult(['standard_fields.price'])),
        ]);
        $enqueueService = $this->makeEmpty(EnqueueServiceInterface::class, [
            'enqueueDocuments' => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class)),
        ]);
        $service = $this->createService(
            $this->createStateService(['mapping-current', 'settings-old', '1.0'], true),
            enqueueService: $enqueueService,
            handler: $handler
        );

        $this->assertTrue(
            $service->execute($service->plan(IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS)))
        );
    }

    public function testGetTargetsKeepsTheIndexOrderAndSkipsUnselectedScopes(): void
    {
        $targets = $this->createService($this->createStateService(self::CURRENT, true))->getTargets([
            DeploymentReindexScope::DOCUMENTS,
            DeploymentReindexScope::ASSETS,
        ]);

        $this->assertSame(['asset', 'document'], array_map(static fn ($target) => $target->getKey(), $targets));
    }

    /**
     * @param array{0: ?string, 1: ?string, 2: ?string} $stored
     */
    private function createStateService(
        array $stored,
        bool $indexExists,
        mixed $store = null
    ): IndexStateServiceInterface {
        return $this->makeEmpty(IndexStateServiceInterface::class, [
            'getCurrentState' => new IndexState(...self::CURRENT),
            'getStoredState' => new IndexState(...$stored),
            'indexExists' => $indexExists,
            'store' => $store ?? static function (): void {
            },
        ]);
    }

    private function createService(
        IndexStateServiceInterface $stateService,
        ?ClassDefinitionReindexServiceInterface $classDefinitionReindexService = null,
        ?EnqueueServiceInterface $enqueueService = null,
        ?IndexHandlerInterface $handler = null
    ): DeploymentReindexService {
        $handler ??= $this->makeEmpty(IndexHandlerInterface::class, ['getMappingDiff' => new MappingDiff([])]);

        return new DeploymentReindexService(
            $stateService,
            $classDefinitionReindexService ?? $this->makeEmpty(ClassDefinitionReindexServiceInterface::class),
            $enqueueService ?? $this->makeEmpty(EnqueueServiceInterface::class),
            $handler,
            $handler,
            $handler,
        );
    }

    private function createClassDefinition(): ClassDefinition
    {
        $classDefinition = new ClassDefinition();
        $classDefinition->setId('TEST');
        $classDefinition->setName('Test');

        return $classDefinition;
    }
}
