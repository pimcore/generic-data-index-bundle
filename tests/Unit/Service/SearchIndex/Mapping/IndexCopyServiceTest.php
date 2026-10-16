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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Mapping;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex\ReindexOutcome;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\MappingDiffService;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class IndexCopyServiceTest extends Unit
{
    private const ALIAS = 'pimcore_data-object_car';

    private const TEXT = ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]];

    public function testUnchangedMappingCopiesWithoutStripping(): void
    {
        $calls = [];
        $service = $this->createService($this->liveMapping(['title' => self::TEXT]), $calls, [
            new ReindexOutcome(ReindexResult::SUCCESS),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping(['title' => self::TEXT]));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([[]], $calls, 'One copy, without a strip list (the request of a plain reindex)');
        $this->assertSame([], $result->getStrippedPaths());
    }

    public function testFieldsFromTheDiffAreStrippedInTheFirstAttempt(): void
    {
        $calls = [];
        $service = $this->createService(
            $this->liveMapping(['price' => self::TEXT, 'count' => ['type' => 'long']]),
            $calls,
            [new ReindexOutcome(ReindexResult::SUCCESS)]
        );

        $result = $service->copy(
            self::ALIAS,
            $this->mapping(['price' => ['type' => 'float'], 'count' => self::TEXT])
        );

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([[['standard_fields', 'price']]], $calls);
        $this->assertSame(['standard_fields.price'], $result->getStrippedPaths());
        $this->assertSame(['standard_fields.count'], $result->getKeptPaths());
        $this->assertSame(1, $result->getAttempts());
    }

    public function testFieldsNamedInParsingFailuresAreAddedAndTheCopyIsRetried(): void
    {
        $calls = [];
        $service = $this->createService($this->liveMapping(['price' => self::TEXT]), $calls, [
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['custom_fields', 'score']]),
            new ReindexOutcome(ReindexResult::SUCCESS),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping(['price' => ['type' => 'float']]));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(
            [
                [['standard_fields', 'price']],
                [['standard_fields', 'price'], ['custom_fields', 'score']],
            ],
            $calls
        );
        $this->assertSame(['standard_fields.price', 'custom_fields.score'], $result->getStrippedPaths());
        $this->assertSame(2, $result->getAttempts());
    }

    public function testAKeptFieldThatFailsInTheCopyIsReportedAsStrippedOnly(): void
    {
        $calls = [];
        $service = $this->createService(
            $this->liveMapping([
                'count' => ['type' => 'long'],
                'tags' => ['properties' => ['a' => ['type' => 'long']]],
            ]),
            $calls,
            [
                new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'count']]),
                new ReindexOutcome(ReindexResult::SUCCESS),
            ]
        );

        $result = $service->copy(self::ALIAS, $this->mapping([
            'count' => ['type' => 'keyword'],
            'tags' => ['type' => 'nested', 'properties' => ['a' => ['type' => 'long']]],
        ]));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(['standard_fields.count'], $result->getStrippedPaths());
        $this->assertSame(
            ['standard_fields.tags'],
            $result->getKeptPaths(),
            'A field stripped by the retry is no longer kept; other kept fields stay'
        );
    }

    public function testRemovedFieldsAreCarriedIntoTheCopyMappingInEveryAttempt(): void
    {
        $mappings = [];
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'getMapping' => $this->liveMapping(['price' => self::TEXT, 'gone' => ['type' => 'date']]),
            'reindexWithoutFields' => static function (string $alias, array $mapping) use (&$mappings): ReindexOutcome {
                $mappings[] = $mapping;

                return count($mappings) === 1
                    ? new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['custom_fields', 'x']])
                    : new ReindexOutcome(ReindexResult::SUCCESS);
            },
        ]);
        $service = new IndexCopyService($searchIndexService, new MappingDiffService(), new NullLogger());

        $result = $service->copy(self::ALIAS, $this->mapping(['price' => self::TEXT]));

        $this->assertTrue($result->isSuccessful());
        $this->assertCount(2, $mappings);
        foreach ($mappings as $mapping) {
            $this->assertSame(['type' => 'date'], $mapping['standard_fields']['properties']['gone']);
            $this->assertSame(self::TEXT, $mapping['standard_fields']['properties']['price']);
        }
    }

    public function testCopyGivesUpWhenAFailureNamesNoNewField(): void
    {
        $calls = [];
        $service = $this->createService($this->liveMapping(['price' => self::TEXT]), $calls, [
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'price']]),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping(['price' => ['type' => 'float']]));

        $this->assertFalse($result->isSuccessful());
        $this->assertCount(1, $calls, 'Repeating the same strip list cannot help');
    }

    public function testCopyGivesUpWhenAFailureNamesNoFieldAtAll(): void
    {
        $calls = [];
        $service = $this->createService($this->liveMapping([]), $calls, [
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, []),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping([]));

        $this->assertFalse($result->isSuccessful());
        $this->assertCount(1, $calls);
    }

    public function testCopyStopsAtTheAttemptLimit(): void
    {
        $calls = [];
        $service = $this->createService($this->liveMapping([]), $calls, [
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['a']]),
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['b']]),
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['c']]),
            new ReindexOutcome(ReindexResult::SUCCESS),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping([]));

        $this->assertFalse($result->isSuccessful());
        $this->assertCount(3, $calls);
        $this->assertSame(3, $result->getAttempts());
    }

    public function testGenuineErrorsPropagate(): void
    {
        $error = new Exception('No alive nodes found in your cluster');
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'getMapping' => $this->liveMapping([]),
            'reindexWithoutFields' => static function () use ($error): ReindexOutcome {
                throw $error;
            },
        ]);
        $service = new IndexCopyService($searchIndexService, new MappingDiffService(), new NullLogger());

        $this->expectExceptionObject($error);

        $service->copy(self::ALIAS, $this->mapping([]));
    }

    public function testAnUnreadableLiveMappingFallsBackToAPlainCopy(): void
    {
        $calls = [];
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'getMapping' => static function (): array {
                throw new Exception('mapping request failed');
            },
            'reindexWithoutFields' => static function (
                string $alias,
                array $mapping,
                array $paths
            ) use (&$calls): ReindexOutcome {
                $calls[] = $paths;

                return new ReindexOutcome(ReindexResult::SUCCESS);
            },
        ]);
        $service = new IndexCopyService($searchIndexService, new MappingDiffService(), new NullLogger());

        $result = $service->copy(self::ALIAS, $this->mapping(['price' => ['type' => 'float']]));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([[]], $calls);
    }

    /**
     * @param ReindexOutcome[] $outcomes
     */
    private function createService(array $liveMapping, array &$calls, array $outcomes): IndexCopyService
    {
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'getMapping' => $liveMapping,
            'reindexWithoutFields' => static function (string $alias, array $mapping, array $paths) use (
                &$calls,
                &$outcomes
            ): ReindexOutcome {
                $calls[] = $paths;

                return array_shift($outcomes);
            },
        ]);

        return new IndexCopyService($searchIndexService, new MappingDiffService(), new NullLogger());
    }

    private function liveMapping(array $fields): array
    {
        return [self::ALIAS . '-odd' => ['mappings' => ['properties' => $this->mapping($fields)]]];
    }

    private function mapping(array $fields): array
    {
        return ['standard_fields' => ['properties' => $fields]];
    }
}
