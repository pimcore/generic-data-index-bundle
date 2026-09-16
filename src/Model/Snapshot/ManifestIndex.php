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
final readonly class ManifestIndex
{
    public function __construct(
        public string $shortName,
        public string $elementType,
        public ?string $classId,
        public string $sourceIndex,
        public int $documentCount,
        public string $file,
        public int $bytes,
        public string $sha256,
    ) {
    }

    public function toArray(): array
    {
        return [
            'short_name' => $this->shortName,
            'element_type' => $this->elementType,
            'class_id' => $this->classId,
            'source_index' => $this->sourceIndex,
            'document_count' => $this->documentCount,
            'file' => $this->file,
            'bytes' => $this->bytes,
            'sha256' => $this->sha256,
        ];
    }

    public static function fromArray(array $data): self
    {
        foreach (['short_name', 'element_type', 'source_index', 'document_count', 'file', 'bytes', 'sha256'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidSnapshotException(sprintf('Manifest index entry is missing "%s"', $key));
            }
        }

        $shortName = (string) $data['short_name'];
        $file = (string) $data['file'];
        self::assertFileMatchesShortName($shortName, $file);

        return new self(
            shortName: $shortName,
            elementType: (string) $data['element_type'],
            classId: isset($data['class_id']) ? (string) $data['class_id'] : null,
            sourceIndex: (string) $data['source_index'],
            documentCount: self::requireNonNegativeInt($data, 'document_count'),
            file: $file,
            bytes: self::requireNonNegativeInt($data, 'bytes'),
            sha256: (string) $data['sha256'],
        );
    }

    /**
     * A manifest entry's `file` must be exactly `<short_name>.ndjson.gz`, matching what the
     * exporter writes. This also prevents two entries from pointing at the same file.
     */
    private static function assertFileMatchesShortName(string $shortName, string $file): void
    {
        $expectedFile = $shortName . '.ndjson.gz';
        if ($file !== $expectedFile) {
            throw new InvalidSnapshotException(sprintf(
                'Manifest index entry "file" ("%s") must match its "short_name" ("%s"), expected "%s"',
                $file,
                $shortName,
                $expectedFile,
            ));
        }
    }

    private static function requireNonNegativeInt(array $data, string $key): int
    {
        $value = $data[$key];
        if (!is_int($value) || $value < 0) {
            throw new InvalidSnapshotException(sprintf(
                'Manifest index entry "%s" must be a non-negative integer',
                $key
            ));
        }

        return $value;
    }
}
