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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexCopyResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingDiff;
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

        return $this->mappingDiffService->diff($liveProperties, $mappingProperties);
    }

    public function copy(string $aliasName, array $mappingProperties): IndexCopyResult
    {
        $diff = $this->diff($aliasName, $mappingProperties);
        $stripPaths = $this->keyed($diff->getStripPathSegments());
        $keptPaths = $diff->getKeptPaths();

        if ($stripPaths !== [] || $keptPaths !== []) {
            $this->pimcoreGenericDataIndexLogger->info(
                sprintf('Copying "%s" into a new index with changed fields', $aliasName),
                ['stripped' => array_keys($stripPaths), 'kept' => $keptPaths]
            );
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $outcome = $this->searchIndexService->reindexWithoutFields(
                $aliasName,
                $mappingProperties,
                array_values($stripPaths)
            );
            if ($outcome->getResult() === ReindexResult::SUCCESS) {
                return new IndexCopyResult(ReindexResult::SUCCESS, array_keys($stripPaths), $keptPaths, $attempt);
            }

            $newPaths = array_diff_key($this->keyed($outcome->getFailingFieldPaths()), $stripPaths);
            if ($newPaths === []) {
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
        }

        return new IndexCopyResult(
            ReindexResult::MAPPING_INCOMPATIBLE,
            array_keys($stripPaths),
            $keptPaths,
            min($attempt, self::MAX_ATTEMPTS)
        );
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
