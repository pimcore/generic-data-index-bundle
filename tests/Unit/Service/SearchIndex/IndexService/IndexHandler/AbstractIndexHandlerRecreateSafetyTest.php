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

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingChangeStrategy;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLock;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The regression test the reported cascade never had. "update:index -r" deleted the live index before the new
 * mapping had been accepted anywhere: a rejected mapping took a measured index from 1,757 fields / 45,144
 * documents to 0 documents with all three aliases still attached, and from there the first saved object got
 * OpenSearch's default dynamic mapping, which has no "system_fields.fullPath.sort" - so the tree sort failed
 * on that shard and returned HTTP 200 with the objects missing.
 *
 * A refusal the bundle cannot count in advance is the case that matters here, and it is not hypothetical: the
 * configured limit is only sent in the create request, so a standing index keeps the limit it was created with
 * (measured: an index carrying 100,000 while the configuration said 250,000). The engine of this test refuses
 * for its own reason while the configured limit allows the mapping, so nothing but the create order can save
 * the live index.
 *
 * @internal
 */
final class AbstractIndexHandlerRecreateSafetyTest extends Unit
{
    private const ALIAS = 'pimcore_data-object_accessorypart';

    private const LIVE_INDEX = self::ALIAS . '-odd';

    private const STANDBY_INDEX = self::ALIAS . '-even';

    private const LIVE_DOCUMENTS = 45144;

    /** What the engine of this test accepts; the configured limit is far above it. */
    private const ENGINE_LIMIT = 10;

    private const STANDARD_FIELDS = 'standard_fields';

    private const PROPERTIES = 'properties';

    /** @var array<string, int> index name => document count */
    private array $indices = [];

    /** @var array<string, string> alias name => index name */
    private array $aliases = [];

    protected function _before(): void
    {
        $this->indices = [self::LIVE_INDEX => self::LIVE_DOCUMENTS];
        $this->aliases = [self::ALIAS => self::LIVE_INDEX];
    }

    public function testARefusedMappingLeavesTheLiveIndexWithItsDocuments(): void
    {
        $handler = $this->createHandler($this->mappingWithLeaves(40));

        $thrown = null;

        try {
            $handler->updateMapping(context: null, forceCreateIndex: true);
        } catch (Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A mapping the engine refuses must fail the update loudly');
        $this->assertSame(
            self::LIVE_DOCUMENTS,
            $this->indices[self::LIVE_INDEX] ?? null,
            'The live index must keep its documents when the new mapping is never accepted'
        );
    }

    public function testARefusedMappingLeavesTheAliasOnAnIndexThatHasTheDocuments(): void
    {
        $handler = $this->createHandler($this->mappingWithLeaves(40));

        try {
            $handler->updateMapping(context: null, forceCreateIndex: true);
        } catch (Exception) {
            // asserted by the sibling test
        }

        $aliasedIndex = $this->aliases[self::ALIAS] ?? null;

        $this->assertNotNull($aliasedIndex, 'The alias must stay attached');
        $this->assertSame(
            self::LIVE_DOCUMENTS,
            $this->indices[$aliasedIndex] ?? null,
            'The alias must not end up on an index the documents were deleted from'
        );
    }

    public function testAnAcceptedMappingStillRecreatesTheIndexAndMovesTheAlias(): void
    {
        $handler = $this->createHandler($this->mappingWithLeaves(2));

        $handler->updateMapping(context: null, forceCreateIndex: true);

        $aliasedIndex = $this->aliases[self::ALIAS] ?? null;

        $this->assertSame(self::STANDBY_INDEX, $aliasedIndex, 'The alias must move to the rebuilt index');
        $this->assertSame(0, $this->indices[self::STANDBY_INDEX] ?? null, 'The rebuilt index starts empty');
        $this->assertArrayNotHasKey(
            self::LIVE_INDEX,
            $this->indices,
            'The index the alias left must be cleaned up, not kept as an orphan'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mappingWithLeaves(int $leaves): array
    {
        $properties = [];
        for ($i = 0; $i < $leaves; $i++) {
            $properties['key' . $i] = ['type' => 'keyword'];
        }

        return [self::STANDARD_FIELDS => ['dynamic' => false, self::PROPERTIES => $properties]];
    }

    /**
     * An engine holding its indices and aliases in memory, refusing a mapping of more than ENGINE_LIMIT fields
     * exactly where OpenSearch refuses one: in the create request and in the mapping update.
     */
    private function createEngine(): SearchIndexServiceInterface
    {
        $refuse = static function (?array $mappings): void {
            if (count($mappings[self::STANDARD_FIELDS][self::PROPERTIES] ?? []) > self::ENGINE_LIMIT) {
                throw new Exception(
                    sprintf('Limit of total fields [%d] has been exceeded', self::ENGINE_LIMIT)
                );
            }
        };

        $engine = null;
        $engine = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => function (string $aliasName, ?string $indexName = null): bool {
                $live = $this->aliases[$aliasName] ?? null;

                return $live !== null && ($indexName === null || $live === $indexName);
            },
            'existsIndex' => fn (string $indexName): bool => isset($this->indices[$indexName]),
            'getCurrentIndexVersion' => function (string $aliasName): string {
                $live = $this->aliases[$aliasName] ?? null;
                if ($live === null) {
                    return '';
                }

                return str_ends_with($live, '-even') ? 'even' : 'odd';
            },
            'deleteIndex' => function (mixed $indexName, bool $silent = false): void {
                unset($this->indices[$indexName]);
                foreach ($this->aliases as $alias => $index) {
                    if ($index === $indexName) {
                        unset($this->aliases[$alias]);
                    }
                }
            },
            'createIndex' => function (string $indexName, ?array $mappings = null) use ($refuse, &$engine) {
                // the adapter deletes the index of that name first, then creates it
                unset($this->indices[$indexName]);
                $refuse($mappings);
                $this->indices[$indexName] = 0;

                return $engine;
            },
            'addAlias' => function (string $aliasName, string $indexName): array {
                $this->aliases[$aliasName] = $indexName;

                return [];
            },
            'switchIndexAlias' => function (string $aliasName, string $newIndexName): void {
                $this->aliases[$aliasName] = $newIndexName;
                foreach ([self::LIVE_INDEX, self::STANDBY_INDEX] as $candidate) {
                    if ($candidate !== $newIndexName) {
                        unset($this->indices[$candidate]);
                    }
                }
            },
            'putMapping' => static function (array $params) use ($refuse): array {
                $refuse([self::STANDARD_FIELDS => $params['body'][self::PROPERTIES][self::STANDARD_FIELDS] ?? []]);

                return [];
            },
            'getMapping' => [],
        ]);

        return $engine;
    }

    /**
     * @param array<string, mixed> $mappingProperties
     */
    private function createHandler(array $mappingProperties): AbstractIndexHandler
    {
        $engine = $this->createEngine();
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getIndexSettings' => ['index' => ['mapping' => ['total_fields' => ['limit' => 1000000]]]],
        ]);

        $handler = new class(
            $engine,
            $config,
            $this->makeEmpty(EventDispatcherInterface::class),
            $this->makeEmpty(IndexMappingServiceInterface::class),
            new IndexCopyService(
                $engine,
                new MappingDiffService(),
                new NullLogger(),
                MappingChangeStrategy::COPY->value
            ),
            new IndexLock(new LockFactory(new InMemoryStore()), 1, 1),
            $mappingProperties
        ) extends AbstractIndexHandler {
            public function __construct(
                SearchIndexServiceInterface $searchIndexService,
                SearchIndexConfigServiceInterface $searchIndexConfigService,
                EventDispatcherInterface $eventDispatcher,
                IndexMappingServiceInterface $indexMappingService,
                IndexCopyService $indexCopyService,
                IndexLock $indexLock,
                private readonly array $properties
            ) {
                parent::__construct(
                    $searchIndexService,
                    $searchIndexConfigService,
                    $eventDispatcher,
                    $indexMappingService,
                    $indexCopyService,
                    $indexLock
                );
            }

            protected function extractMappingProperties(mixed $context = null): array
            {
                return $this->properties;
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'pimcore_data-object_accessorypart';
            }
        };
        $handler->setLogger(new NullLogger());

        return $handler;
    }
}
