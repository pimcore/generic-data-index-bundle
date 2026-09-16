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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;

/**
 * @internal
 */
final readonly class Manifest
{
    public const FORMAT_VERSION = 1;

    private const REQUIRED_KEYS = [
        'format_version', 'created_at', 'generic_data_index_version', 'pimcore_version', 'client_type',
        'index_prefix', 'queue_entries_before', 'queue_entries_after', 'duration_seconds',
        'class_mapping_checksums', 'indices',
    ];

    /**
     * @param array<string, int> $classMappingChecksums class definition id => mapping checksum
     * @param ManifestIndex[] $indices
     */
    public function __construct(
        public string $createdAt,
        public string $genericDataIndexVersion,
        public string $pimcoreVersion,
        public string $clientType,
        public string $indexPrefix,
        public int $queueEntriesBefore,
        public int $queueEntriesAfter,
        public int $durationSeconds,
        public array $classMappingChecksums,
        public array $indices,
        public int $formatVersion = self::FORMAT_VERSION,
    ) {
    }

    public function getIndex(string $shortName): ?ManifestIndex
    {
        foreach ($this->indices as $index) {
            if ($index->shortName === $shortName) {
                return $index;
            }
        }

        return null;
    }

    /** @param ManifestIndex[] $indices */
    public function withIndices(array $indices): self
    {
        return new self(
            createdAt: $this->createdAt,
            genericDataIndexVersion: $this->genericDataIndexVersion,
            pimcoreVersion: $this->pimcoreVersion,
            clientType: $this->clientType,
            indexPrefix: $this->indexPrefix,
            queueEntriesBefore: $this->queueEntriesBefore,
            queueEntriesAfter: $this->queueEntriesAfter,
            durationSeconds: $this->durationSeconds,
            classMappingChecksums: $this->classMappingChecksums,
            indices: array_values($indices),
            formatVersion: $this->formatVersion,
        );
    }

    /** @param array<string, int> $classMappingChecksums */
    public function withClassMappingChecksums(array $classMappingChecksums): self
    {
        return new self(
            createdAt: $this->createdAt,
            genericDataIndexVersion: $this->genericDataIndexVersion,
            pimcoreVersion: $this->pimcoreVersion,
            clientType: $this->clientType,
            indexPrefix: $this->indexPrefix,
            queueEntriesBefore: $this->queueEntriesBefore,
            queueEntriesAfter: $this->queueEntriesAfter,
            durationSeconds: $this->durationSeconds,
            classMappingChecksums: $classMappingChecksums,
            indices: $this->indices,
            formatVersion: $this->formatVersion,
        );
    }

    public function toArray(): array
    {
        return [
            'format_version' => $this->formatVersion,
            'created_at' => $this->createdAt,
            'generic_data_index_version' => $this->genericDataIndexVersion,
            'pimcore_version' => $this->pimcoreVersion,
            'client_type' => $this->clientType,
            'index_prefix' => $this->indexPrefix,
            'queue_entries_before' => $this->queueEntriesBefore,
            'queue_entries_after' => $this->queueEntriesAfter,
            'duration_seconds' => $this->durationSeconds,
            'class_mapping_checksums' => $this->classMappingChecksums,
            'indices' => array_map(static fn (ManifestIndex $index) => $index->toArray(), $this->indices),
        ];
    }

    public static function fromArray(array $data): self
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidSnapshotException(sprintf('Manifest is missing "%s"', $key));
            }
        }

        if ($data['format_version'] !== self::FORMAT_VERSION) {
            throw new InvalidSnapshotException(sprintf(
                'Unsupported snapshot format version %s, this version of the bundle reads version %d',
                var_export($data['format_version'], true),
                self::FORMAT_VERSION
            ));
        }

        if (!is_array($data['indices']) || !is_array($data['class_mapping_checksums'])) {
            throw new InvalidSnapshotException('Manifest "indices" and "class_mapping_checksums" must be arrays');
        }

        foreach ($data['indices'] as $index) {
            if (!is_array($index)) {
                throw new InvalidSnapshotException('Manifest "indices" entries must be arrays');
            }
        }

        $checksums = self::requireIntChecksums($data['class_mapping_checksums']);
        $indices = array_map(static fn (array $index) => ManifestIndex::fromArray($index), $data['indices']);
        self::assertNoDuplicateIndices($indices);

        return new self(
            createdAt: (string) $data['created_at'],
            genericDataIndexVersion: (string) $data['generic_data_index_version'],
            pimcoreVersion: (string) $data['pimcore_version'],
            clientType: (string) $data['client_type'],
            indexPrefix: (string) $data['index_prefix'],
            queueEntriesBefore: self::requireNonNegativeInt($data, 'queue_entries_before'),
            queueEntriesAfter: self::requireNonNegativeInt($data, 'queue_entries_after'),
            durationSeconds: self::requireNonNegativeInt($data, 'duration_seconds'),
            classMappingChecksums: $checksums,
            indices: $indices,
            formatVersion: self::FORMAT_VERSION,
        );
    }

    /**
     * Rejects a manifest whose indices list contains two entries for the same short name, or two
     * entries that would target the same local index: for `dataObject` entries that's the class
     * id (or the shared folder index when `class_id` is null), for every other element type the
     * element type itself is the identity, since those indices are singletons.
     *
     * @param ManifestIndex[] $indices
     */
    private static function assertNoDuplicateIndices(array $indices): void
    {
        $seenShortNames = [];
        $seenTargets = [];
        foreach ($indices as $index) {
            if (isset($seenShortNames[$index->shortName])) {
                throw new InvalidSnapshotException(sprintf(
                    'Manifest has more than one index entry named "%s"',
                    $index->shortName,
                ));
            }
            $seenShortNames[$index->shortName] = true;

            $target = self::targetIdentity($index);
            if (isset($seenTargets[$target])) {
                throw new InvalidSnapshotException(sprintf(
                    'Manifest has more than one index entry for "%s"',
                    $target,
                ));
            }
            $seenTargets[$target] = true;
        }
    }

    private static function targetIdentity(ManifestIndex $index): string
    {
        return IndexIdentity::fromManifestIndex($index)->key();
    }

    private static function requireNonNegativeInt(array $data, string $key): int
    {
        $value = $data[$key];
        if (!is_int($value) || $value < 0) {
            throw new InvalidSnapshotException(sprintf('Manifest "%s" must be a non-negative integer', $key));
        }

        return $value;
    }

    /**
     * @return array<string, int>
     */
    private static function requireIntChecksums(array $classMappingChecksums): array
    {
        $checksums = [];
        foreach ($classMappingChecksums as $classId => $checksum) {
            if (!is_int($checksum)) {
                throw new InvalidSnapshotException(sprintf(
                    'Manifest "class_mapping_checksums" value for "%s" must be an integer',
                    (string) $classId
                ));
            }
            $checksums[(string) $classId] = $checksum;
        }

        return $checksums;
    }
}
