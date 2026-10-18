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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\MappingFieldChangeAction;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexCopyResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingFieldChange;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final readonly class IndexCopyService implements IndexCopyServiceInterface
{
    /** Copy attempts: the first one with the diff, the others add fields named in parsing failures. */
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private SearchIndexServiceInterface $searchIndexService,
        private MappingDiffServiceInterface $mappingDiffService,
        private LoggerInterface $pimcoreGenericDataIndexLogger,
    ) {
    }

    public function diff(string $aliasName, array $mappingProperties): MappingDiff
    {
        try {
            $liveMapping = $this->searchIndexService->getMapping($aliasName);
        } catch (Exception $e) {
            // Without the live mapping the copy runs as before; the failure loop still applies.
            $this->pimcoreGenericDataIndexLogger->warning(
                sprintf('Reading the mapping of "%s" failed, copying without a field diff', $aliasName),
                ['exception' => $e]
            );

            return new MappingDiff([]);
        }

        // The engine answers an alias with {"<concrete index>": {"mappings": {"properties": ...}}}.
        $index = reset($liveMapping);
        $liveProperties = is_array($index) ? ($index['mappings']['properties'] ?? []) : [];

        return $this->withSystemFieldsProtected(
            $this->mappingDiffService->diff($liveProperties, $mappingProperties)
        );
    }

    public function copy(string $aliasName, array $mappingProperties): IndexCopyResult
    {
        $diff = $this->diff($aliasName, $mappingProperties);
        $stripPaths = $this->keyed($diff->getStripPathSegments());
        $keptPaths = $diff->getKeptPaths();
        $rebuildPaths = $diff->getRebuildPaths();

        if ($stripPaths !== [] || $keptPaths !== [] || $diff->getProtectedPaths() !== []) {
            $this->pimcoreGenericDataIndexLogger->info(
                sprintf('Copying "%s" into a new index with changed fields', $aliasName),
                ['stripped' => array_keys($stripPaths), 'kept' => $keptPaths, 'protected' => $diff->getProtectedPaths()]
            );
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $outcome = $this->searchIndexService->reindexWithoutFields(
                $aliasName,
                $mappingProperties,
                array_values($stripPaths)
            );
            if ($outcome->getResult() === ReindexResult::SUCCESS) {
                return new IndexCopyResult(
                    ReindexResult::SUCCESS,
                    array_keys($stripPaths),
                    $keptPaths,
                    $attempt,
                    $rebuildPaths
                );
            }

            $failingPaths = $this->strippableOnly($this->keyed(
                $this->mappingDiffService->resolveSourceFields($mappingProperties, $outcome->getFailingFieldPaths())
            ));
            $newPaths = array_diff_key(
                $failingPaths + $this->removedFieldsIfOneFailed($failingPaths, $diff->getRemovedPathSegments()),
                $stripPaths
            );
            if ($newPaths === [] || $attempt === self::MAX_ATTEMPTS) {
                break;
            }

            $this->pimcoreGenericDataIndexLogger->warning(
                sprintf(
                    'Copying "%s" failed on fields the mapping diff did not report, retrying without them',
                    $aliasName
                ),
                ['fields' => array_keys($newPaths), 'attempt' => $attempt]
            );
            $stripPaths += $newPaths;
            $keptPaths = $this->withoutStripped($keptPaths, array_keys($newPaths));
            $rebuildPaths = $this->withoutStripped($rebuildPaths, array_keys($newPaths));
        }

        return new IndexCopyResult(
            ReindexResult::MAPPING_INCOMPATIBLE,
            array_keys($stripPaths),
            $keptPaths,
            min($attempt, self::MAX_ATTEMPTS),
            $rebuildPaths
        );
    }

    /**
     * The engine maps removed fields again from the first copied document, and values that disagree
     * ("2026-01-01" and "abc") fail - one field per attempt. When one removed field fails, all removed
     * fields are left out of the next attempt; they are no longer part of the mapping anyway.
     *
     * @param array<string, string[]> $failingPaths
     * @param string[][] $removedPaths
     *
     * @return array<string, string[]>
     */
    private function removedFieldsIfOneFailed(array $failingPaths, array $removedPaths): array
    {
        $removedPaths = array_values($this->strippableOnly($this->keyed($removedPaths)));
        foreach ($failingPaths as $failing) {
            foreach ($removedPaths as $removed) {
                if (array_slice($failing, 0, count($removed)) === $removed) {
                    return $this->keyed($removedPaths);
                }
            }
        }

        return [];
    }

    /**
     * Class fields and custom fields are stripped; the rebuild from the database refills both, the custom
     * fields through the indexing subscribers. System fields are never stripped: a document without its id
     * or class breaks search results, which is worse than the recreation that follows. Fields outside these
     * groups (for example dynamically mapped ones) are not stripped either.
     *
     * @param array<string, string[]> $paths
     *
     * @return array<string, string[]>
     */
    private function strippableOnly(array $paths): array
    {
        $strippable = [FieldCategory::STANDARD_FIELDS->value, FieldCategory::CUSTOM_FIELDS->value];

        return array_filter(
            $paths,
            static fn (array $path): bool => in_array($path[0], $strippable, true) && count($path) > 1
        );
    }

    /**
     * The first attempt follows the rule of the retries: a change the diff would strip on a field that is never
     * stripped is copied as it is. If the engine rejects its values, the copy fails and the index is recreated.
     */
    private function withSystemFieldsProtected(MappingDiff $diff): MappingDiff
    {
        $changes = [];
        foreach ($diff->getChanges() as $change) {
            $strippable = $this->strippableOnly([$change->getPath() => $change->getPathSegments()]) !== [];
            $changes[] = $change->getAction() === MappingFieldChangeAction::STRIP && !$strippable
                ? new MappingFieldChange(
                    $change->getPathSegments(),
                    $change->getOldShape(),
                    $change->getNewShape(),
                    MappingFieldChangeAction::PROTECT
                )
                : $change;
        }

        return new MappingDiff($changes, $diff->getRemovedPathSegments());
    }

    /**
     * A kept field that failed in the copy is stripped after all; so is everything below a stripped path.
     *
     * @param string[] $keptPaths
     * @param string[] $strippedPaths
     *
     * @return string[]
     */
    private function withoutStripped(array $keptPaths, array $strippedPaths): array
    {
        return array_values(array_filter(
            $keptPaths,
            static function (string $kept) use ($strippedPaths): bool {
                foreach ($strippedPaths as $stripped) {
                    if ($kept === $stripped || str_starts_with($kept, $stripped . '.')) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * @param string[][] $paths
     *
     * @return array<string, string[]> keyed by dotted path, deduplicated
     */
    private function keyed(array $paths): array
    {
        $keyed = [];
        foreach ($paths as $path) {
            $keyed[implode('.', $path)] = $path;
        }

        return $keyed;
    }
}
