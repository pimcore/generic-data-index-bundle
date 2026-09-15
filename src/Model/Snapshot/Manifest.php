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

        $checksums = [];
        foreach ($data['class_mapping_checksums'] as $classId => $checksum) {
            if (!is_int($checksum) && !(is_string($checksum) && ctype_digit($checksum))) {
                throw new InvalidSnapshotException(sprintf(
                    'Manifest "class_mapping_checksums" value for "%s" must be int-like',
                    (string) $classId
                ));
            }
            $checksums[(string) $classId] = (int) $checksum;
        }

        return new self(
            createdAt: (string) $data['created_at'],
            genericDataIndexVersion: (string) $data['generic_data_index_version'],
            pimcoreVersion: (string) $data['pimcore_version'],
            clientType: (string) $data['client_type'],
            indexPrefix: (string) $data['index_prefix'],
            queueEntriesBefore: (int) $data['queue_entries_before'],
            queueEntriesAfter: (int) $data['queue_entries_after'],
            durationSeconds: (int) $data['duration_seconds'],
            classMappingChecksums: $checksums,
            indices: array_map(static fn (array $index) => ManifestIndex::fromArray($index), $data['indices']),
            formatVersion: self::FORMAT_VERSION,
        );
    }
}
