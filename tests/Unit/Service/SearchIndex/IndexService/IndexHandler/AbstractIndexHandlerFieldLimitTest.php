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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingChangeStrategy;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\TotalFieldsLimitException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLock;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\TotalFieldsLimitGuard;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The engine checks "total_fields.limit" after it has parsed the mapping into its heap: at ETIM scale with one
 * language the cluster-manager thread died with java.lang.OutOfMemoryError in ObjectMapper$TypeParser while
 * parsing, and the limit check never ran. So the limit cannot be the guard - the mapping has to be counted and
 * refused on this side, before the engine is asked for it.
 *
 * @internal
 */
final class AbstractIndexHandlerFieldLimitTest extends Unit
{
    private const STANDARD_FIELDS = 'standard_fields';

    private const PROPERTIES = 'properties';

    private const KEYWORD = 'keyword';

    private const HUGE_FIELD = 'technicalAttributes';

    public function testAMappingOverTheConfiguredLimitIsRefusedBeforeTheEngineIsAsked(): void
    {
        $handler = $this->createHandler(
            limit: 10,
            mappingProperties: $this->mappingWithFieldCount(40),
            engineExpectsNoRequest: true
        );

        $thrown = null;

        try {
            $handler->updateMapping(forceCreateIndex: true);
        } catch (TotalFieldsLimitException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(
            TotalFieldsLimitException::class,
            $thrown,
            'A mapping over the configured total fields limit must be refused, not sent to the engine'
        );
    }

    public function testTheRefusalNamesTheCountTheLimitAndTheOffendingColumn(): void
    {
        $handler = $this->createHandler(
            limit: 10,
            mappingProperties: $this->mappingWithFieldCount(40),
            engineExpectsNoRequest: true
        );

        $message = '';

        try {
            $handler->updateMapping(forceCreateIndex: true);
        } catch (TotalFieldsLimitException $e) {
            $message = $e->getMessage();
        }

        // 40 leaves + the nested node + standard_fields + the two empty category nodes
        $this->assertStringContainsString('44 fields', $message, 'The counted fields of the mapping must be named');
        $this->assertStringContainsString('limit of 10', $message, 'The configured limit must be named');
        $this->assertStringContainsString(
            self::STANDARD_FIELDS . '.' . self::HUGE_FIELD,
            $message,
            'The column contributing the most fields must be named'
        );
    }

    public function testAMappingWithinTheLimitIsStillApplied(): void
    {
        $createdIndices = [];
        $handler = $this->createHandler(
            limit: 1000,
            mappingProperties: $this->mappingWithFieldCount(40),
            createdIndices: $createdIndices
        );

        $handler->updateMapping(forceCreateIndex: true);

        $this->assertNotEmpty($createdIndices, 'A mapping within the limit must still reach the engine');
    }

    public function testAnUnconfiguredLimitLeavesTheDecisionToTheEngine(): void
    {
        $createdIndices = [];
        $handler = $this->createHandler(
            limit: null,
            mappingProperties: $this->mappingWithFieldCount(40),
            createdIndices: $createdIndices
        );

        $handler->updateMapping(forceCreateIndex: true);

        $this->assertNotEmpty($createdIndices, 'Without a configured limit nothing is refused on this side');
    }

    /**
     * The mapping the preflight counted is the mapping the engine is sent. An in-place update re-extracting it
     * would send a mapping nobody counted: the caller-supplied one passes the count and the extracted one,
     * which is what reaches putMapping, does not.
     */
    public function testTheInPlaceUpdateSendsTheMappingTheLimitWasCheckedOn(): void
    {
        $sentProperties = null;
        $handler = $this->createHandlerWithLiveIndex(
            limit: 10,
            extractedProperties: $this->mappingWithFieldCount(40),
            sentProperties: $sentProperties
        );

        $suppliedProperties = $this->mappingWithFieldCount(2);

        $handler->updateMappingWithResult(null, $suppliedProperties);

        $this->assertSame(
            $suppliedProperties,
            $sentProperties,
            'putMapping must be sent the mapping the field count was decided on, not a freshly extracted one'
        );
    }

    /**
     * A handler whose alias is attached to a live index, so the mapping goes in place, and whose putMapping
     * records the properties it was sent.
     *
     * @param array<string, mixed> $extractedProperties
     * @param array<string, mixed>|null $sentProperties
     */
    private function createHandlerWithLiveIndex(
        int $limit,
        array $extractedProperties,
        ?array &$sentProperties
    ): AbstractIndexHandler {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'existsIndex' => true,
            'getCurrentIndexVersion' => 'odd',
            'getMapping' => [],
            'putMapping' => static function (array $params) use (&$sentProperties): array {
                $sentProperties = $params['body'][self::PROPERTIES] ?? null;

                return [];
            },
        ]);

        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getIndexSettings' => ['index' => ['mapping' => ['total_fields' => ['limit' => $limit]]]],
        ]);

        $handler = new class($search, $config, $this->makeEmpty(EventDispatcherInterface::class), $this->makeEmpty(IndexMappingServiceInterface::class), new IndexCopyService($search, new MappingDiffService(), new NullLogger(), MappingChangeStrategy::COPY->value), new IndexLock(new LockFactory(new InMemoryStore()), 1, 1), new TotalFieldsLimitGuard($config), $extractedProperties) extends AbstractIndexHandler {
            public function __construct(
                SearchIndexServiceInterface $searchIndexService,
                SearchIndexConfigServiceInterface $searchIndexConfigService,
                EventDispatcherInterface $eventDispatcher,
                IndexMappingServiceInterface $indexMappingService,
                IndexCopyService $indexCopyService,
                IndexLock $indexLock,
                TotalFieldsLimitGuard $totalFieldsLimitGuard,
                private readonly array $properties
            ) {
                parent::__construct(
                    $searchIndexService,
                    $searchIndexConfigService,
                    $eventDispatcher,
                    $indexMappingService,
                    $indexCopyService,
                    $indexLock,
                    $totalFieldsLimitGuard
                );
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return $this->properties;
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());

        return $handler;
    }

    /**
     * @return array<string, mixed>
     */
    private function mappingWithFieldCount(int $leaves): array
    {
        $keys = [];
        for ($i = 0; $i < $leaves; $i++) {
            $keys['key' . $i] = ['type' => self::KEYWORD];
        }

        return [
            'system_fields' => [self::PROPERTIES => []],
            self::STANDARD_FIELDS => [
                'dynamic' => false,
                self::PROPERTIES => [
                    self::HUGE_FIELD => [
                        'type' => 'nested',
                        'dynamic' => false,
                        self::PROPERTIES => $keys,
                    ],
                ],
            ],
            'custom_fields' => [self::PROPERTIES => []],
        ];
    }

    /**
     * @param array<string, mixed> $mappingProperties
     * @param string[] $createdIndices
     */
    private function createHandler(
        ?int $limit,
        array $mappingProperties,
        bool $engineExpectsNoRequest = false,
        array &$createdIndices = []
    ): AbstractIndexHandler {
        $indexSettings = $limit === null
            ? []
            : ['index' => ['mapping' => ['total_fields' => ['limit' => $limit]]]];

        $fluent = $this->makeEmpty(SearchIndexServiceInterface::class, ['addAlias' => []]);
        $createIndex = static function (string $indexName) use (&$createdIndices, $fluent): SearchIndexServiceInterface {
            $createdIndices[] = $indexName;

            return $fluent;
        };

        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => false,
            'existsIndex' => false,
            'deleteIndex' => null,
            'getCurrentIndexVersion' => '',
            'addAlias' => [],
            'createIndex' => $engineExpectsNoRequest ? Expected::never() : $createIndex,
            'putMapping' => $engineExpectsNoRequest ? Expected::never() : [],
        ]);

        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getIndexSettings' => $indexSettings,
        ]);

        $handler = new class($search, $config, $this->makeEmpty(EventDispatcherInterface::class), $this->makeEmpty(IndexMappingServiceInterface::class), new IndexCopyService($search, new MappingDiffService(), new NullLogger(), MappingChangeStrategy::COPY->value), new IndexLock(new LockFactory(new InMemoryStore()), 1, 1), new TotalFieldsLimitGuard($config), $mappingProperties) extends AbstractIndexHandler {
            public function __construct(
                SearchIndexServiceInterface $searchIndexService,
                SearchIndexConfigServiceInterface $searchIndexConfigService,
                EventDispatcherInterface $eventDispatcher,
                IndexMappingServiceInterface $indexMappingService,
                IndexCopyService $indexCopyService,
                IndexLock $indexLock,
                TotalFieldsLimitGuard $totalFieldsLimitGuard,
                private readonly array $properties
            ) {
                parent::__construct(
                    $searchIndexService,
                    $searchIndexConfigService,
                    $eventDispatcher,
                    $indexMappingService,
                    $indexCopyService,
                    $indexLock,
                    $totalFieldsLimitGuard
                );
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return $this->properties;
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test_alias';
            }
        };
        $handler->setLogger(new NullLogger());

        return $handler;
    }
}
