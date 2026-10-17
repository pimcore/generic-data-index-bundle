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
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'score']]),
            new ReindexOutcome(ReindexResult::SUCCESS),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping(['price' => ['type' => 'float']]));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(
            [
                [['standard_fields', 'price']],
                [['standard_fields', 'price'], ['standard_fields', 'score']],
            ],
            $calls
        );
        $this->assertSame(['standard_fields.price', 'standard_fields.score'], $result->getStrippedPaths());
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

    /**
     * The engine maps removed fields again from the first copied document; values that disagree
     * ("2026-01-01" and "abc") fail, one field per attempt. A failure on one removed field strips
     * all of them in the next attempt.
     */
    public function testAFailureOnARemovedFieldStripsAllRemovedFields(): void
    {
        $calls = [];
        $service = $this->createService(
            $this->liveMapping([
                'price' => self::TEXT,
                'gone1' => self::TEXT,
                'gone2' => self::TEXT,
                'gone3' => self::TEXT,
                'gone4' => self::TEXT,
            ]),
            $calls,
            [
                new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'gone2']]),
                new ReindexOutcome(ReindexResult::SUCCESS),
            ]
        );

        $result = $service->copy(self::ALIAS, $this->mapping(['price' => self::TEXT]));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2, $result->getAttempts());
        $this->assertSame([], $calls[0], 'Removed fields are copied as before while nothing fails');
        $this->assertEqualsCanonicalizing(
            ['standard_fields.gone1', 'standard_fields.gone2', 'standard_fields.gone3', 'standard_fields.gone4'],
            $result->getStrippedPaths()
        );
    }

    public function testAFailingSubFieldStripsItsFieldAndNoLongerKeepsIt(): void
    {
        $calls = [];
        $service = $this->createService($this->liveMapping(['title' => ['type' => 'keyword']]), $calls, [
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'title', 'num']]),
            new ReindexOutcome(ReindexResult::SUCCESS),
        ]);

        $result = $service->copy(
            self::ALIAS,
            $this->mapping(['title' => ['type' => 'text', 'fields' => ['num' => ['type' => 'long']]]])
        );

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([[], [['standard_fields', 'title']]], $calls);
        $this->assertSame(['standard_fields.title'], $result->getStrippedPaths());
        $this->assertSame([], $result->getKeptPaths(), 'The kept field failed and was stripped');
    }

    /**
     * The engine maps some system fields dynamically (classId, className, checksum); they are not
     * in the generated mapping but must never be stripped with the removed class fields.
     */
    public function testRemovedFieldsOutsideTheClassFieldsAreNeverStripped(): void
    {
        $calls = [];
        $service = $this->createService(
            [self::ALIAS . '-odd' => ['mappings' => ['properties' => [
                'system_fields' => ['properties' => ['id' => ['type' => 'long'], 'classId' => self::TEXT]],
                'custom_fields' => ['properties' => ['extra' => self::TEXT]],
                'standard_fields' => ['properties' => ['gone1' => self::TEXT, 'gone2' => self::TEXT]],
            ]]]],
            $calls,
            [
                new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'gone1']]),
                new ReindexOutcome(ReindexResult::SUCCESS),
            ]
        );

        $result = $service->copy(self::ALIAS, [
            'system_fields' => ['properties' => ['id' => ['type' => 'long']]],
            'standard_fields' => ['properties' => []],
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertEqualsCanonicalizing(
            ['standard_fields.gone1', 'standard_fields.gone2'],
            $result->getStrippedPaths()
        );
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
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'a']]),
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'b']]),
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['standard_fields', 'c']]),
            new ReindexOutcome(ReindexResult::SUCCESS),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping([]));

        $this->assertFalse($result->isSuccessful());
        $this->assertCount(3, $calls);
        $this->assertSame(3, $result->getAttempts());
        $this->assertSame(
            ['standard_fields.a', 'standard_fields.b'],
            $result->getStrippedPaths(),
            'Only paths a copy attempt was run without'
        );
    }

    /**
     * System and custom fields are never stripped on a failure: a document without its id or
     * class id breaks search results, which is worse than the recreation that follows.
     */
    public function testFailuresOnSystemOrCustomFieldsDoNotStripThem(): void
    {
        $calls = [];
        $service = $this->createService($this->liveMapping(['title' => self::TEXT]), $calls, [
            new ReindexOutcome(ReindexResult::MAPPING_INCOMPATIBLE, [['system_fields', 'id'], ['custom_fields', 'x']]),
            new ReindexOutcome(ReindexResult::SUCCESS),
        ]);

        $result = $service->copy(self::ALIAS, $this->mapping(['title' => self::TEXT]));

        $this->assertFalse($result->isSuccessful());
        $this->assertCount(1, $calls);
        $this->assertSame([], $result->getStrippedPaths());
    }

    /**
     * The rule of the retries applies to the first attempt as well: only class fields are stripped.
     * A changed system or custom field is copied as it is; if the engine rejects it, the copy fails
     * and the index is recreated, as for any failure that no class field explains.
     */
    public function testChangedSystemCustomAndRootFieldsAreCopiedAsTheyAre(): void
    {
        $calls = [];
        $service = $this->createService(
            [self::ALIAS . '-odd' => ['mappings' => ['properties' => [
                'system_fields' => ['properties' => ['id' => ['type' => 'long']]],
                'custom_fields' => ['properties' => ['x' => ['type' => 'keyword']]],
                'root' => ['type' => 'keyword'],
                'standard_fields' => ['properties' => ['price' => self::TEXT]],
            ]]]],
            $calls,
            [new ReindexOutcome(ReindexResult::SUCCESS)]
        );

        $result = $service->copy(self::ALIAS, [
            'system_fields' => ['properties' => ['id' => ['type' => 'integer']]],
            'custom_fields' => ['properties' => ['x' => ['type' => 'long']]],
            'root' => ['type' => 'long'],
            'standard_fields' => ['properties' => ['price' => ['type' => 'float']]],
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([[['standard_fields', 'price']]], $calls, 'Only the class field is stripped');
        $this->assertSame(['standard_fields.price'], $result->getStrippedPaths());
        $this->assertSame(['custom_fields.x', 'root', 'system_fields.id'], $result->getKeptPaths());
    }

    public function testTheDiffForTheDryRunReportsChangedSystemFieldsAsKept(): void
    {
        $calls = [];
        $service = $this->createService(
            [self::ALIAS . '-odd' => ['mappings' => ['properties' => [
                'system_fields' => ['properties' => ['id' => ['type' => 'long']]],
                'standard_fields' => ['properties' => ['price' => self::TEXT]],
            ]]]],
            $calls,
            []
        );

        $diff = $service->diff(self::ALIAS, [
            'system_fields' => ['properties' => ['id' => ['type' => 'integer']]],
            'standard_fields' => ['properties' => ['price' => ['type' => 'float']]],
        ]);

        $this->assertSame(['standard_fields.price'], $diff->getStripPaths());
        $this->assertSame(['system_fields.id'], $diff->getKeptPaths());
        $this->assertSame([], $calls);
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
