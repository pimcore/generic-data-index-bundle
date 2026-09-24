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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;

/**
 * @internal
 */
final readonly class ManifestIndex
{
    public function __construct(
        private string $shortName,
        private string $elementType,
        private ?string $classId,
        private string $sourceIndex,
        private int $documentCount,
        private string $file,
        private int $bytes,
        private string $sha256,
    ) {
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function getElementType(): string
    {
        return $this->elementType;
    }

    public function getClassId(): ?string
    {
        return $this->classId;
    }

    public function getSourceIndex(): string
    {
        return $this->sourceIndex;
    }

    public function getDocumentCount(): int
    {
        return $this->documentCount;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    public function getSha256(): string
    {
        return $this->sha256;
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

        $elementType = (string) $data['element_type'];
        $classId = isset($data['class_id']) ? (string) $data['class_id'] : null;
        self::assertValidIdentity($shortName, $elementType, $classId);

        return new self(
            shortName: $shortName,
            elementType: $elementType,
            classId: $classId,
            sourceIndex: (string) $data['source_index'],
            documentCount: self::requireNonNegativeInt($data, 'document_count'),
            file: $file,
            bytes: self::requireNonNegativeInt($data, 'bytes'),
            sha256: (string) $data['sha256'],
        );
    }

    /**
     * A manifest entry's identity (`short_name` + `class_id`) must match what the exporter would
     * have written for its `element_type`, so a malformed entry cannot be resolved to the wrong
     * local index (e.g. a class entry without a `class_id` silently recreating the folder index).
     */
    private static function assertValidIdentity(string $shortName, string $elementType, ?string $classId): void
    {
        $identity = new IndexIdentity($elementType, $classId);

        if ($identity->isDataObject()) {
            self::assertValidDataObjectIdentity($identity, $shortName);

            return;
        }

        self::assertValidSingletonIdentity($identity, $shortName);
    }

    private static function assertValidDataObjectIdentity(IndexIdentity $identity, string $shortName): void
    {
        if (!$identity->isClassIndex()) {
            if (!$identity->acceptsShortName($shortName)) {
                throw new InvalidSnapshotException(sprintf(
                    'Manifest index entry "%s" has element_type "dataObject" without a "class_id", ' .
                    'so its "short_name" must be "%s"',
                    $shortName,
                    IndexName::DATA_OBJECT_FOLDER->value,
                ));
            }

            return;
        }

        if (!$identity->acceptsShortName($shortName)) {
            throw new InvalidSnapshotException(sprintf(
                'Manifest index entry "%s" has element_type "dataObject" with class_id "%s", so its ' .
                '"short_name" must start with "%s" and must not be the folder index name "%s"',
                $shortName,
                $identity->getClassId(),
                SearchIndexConfigService::CLASS_INDEX_PREFIX,
                IndexName::DATA_OBJECT_FOLDER->value,
            ));
        }
    }

    private static function assertValidSingletonIdentity(IndexIdentity $identity, string $shortName): void
    {
        $expectedShortName = $identity->expectedShortName();
        if ($expectedShortName === null) {
            return;
        }

        if ($identity->getClassId() !== null) {
            throw new InvalidSnapshotException(sprintf(
                'Manifest index entry "%s" has element_type "%s", which must not have a "class_id"',
                $shortName,
                $identity->getElementType(),
            ));
        }

        if (!$identity->acceptsShortName($shortName)) {
            throw new InvalidSnapshotException(sprintf(
                'Manifest index entry "%s" has element_type "%s", so its "short_name" must be "%s"',
                $shortName,
                $identity->getElementType(),
                $expectedShortName,
            ));
        }
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
