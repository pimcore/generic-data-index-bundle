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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;

final class ManifestTest extends Unit
{
    public function testRoundTripKeepsEveryField(): void
    {
        $manifest = $this->manifest();

        $restored = Manifest::fromArray(json_decode(json_encode($manifest->toArray()), true));

        $this->assertSame($manifest->toArray(), $restored->toArray());
        $this->assertSame(1, $restored->getFormatVersion());
        $this->assertSame(['PR' => 1830112233], $restored->getClassMappingChecksums());
        $index = $restored->getIndex('data-object_product');
        $this->assertInstanceOf(ManifestIndex::class, $index);
        $this->assertSame('PR', $index->getClassId());
        $this->assertSame(602114, $index->getDocumentCount());
        $this->assertNull($restored->getIndex('nope'));
    }

    public function testRejectsUnknownFormatVersion(): void
    {
        $data = $this->manifest()->toArray();
        $data['format_version'] = 2;

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('format version 2');
        Manifest::fromArray($data);
    }

    public function testRejectsMissingRequiredKey(): void
    {
        $data = $this->manifest()->toArray();
        unset($data['indices']);

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('indices');
        Manifest::fromArray($data);
    }

    public function testRejectsIndexEntryWithoutFile(): void
    {
        $data = $this->manifest()->toArray();
        unset($data['indices'][0]['file']);

        $this->expectException(InvalidSnapshotException::class);
        Manifest::fromArray($data);
    }

    public function testRejectsNonArrayIndexEntry(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'] = [1, 2];

        $this->expectException(InvalidSnapshotException::class);
        Manifest::fromArray($data);
    }

    public function testRejectsNonIntegerDocumentCount(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][0]['document_count'] = '1oops';

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('document_count');
        Manifest::fromArray($data);
    }

    public function testRejectsNegativeBytes(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][0]['bytes'] = -1;

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('bytes');
        Manifest::fromArray($data);
    }

    public function testRejectsNonIntegerQueueEntriesBefore(): void
    {
        $data = $this->manifest()->toArray();
        $data['queue_entries_before'] = '3';

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('queue_entries_before');
        Manifest::fromArray($data);
    }

    public function testRejectsDuplicateAssetEntries(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('asset', 'asset', null)->toArray();
        $data['indices'][] = $this->indexEntry('asset', 'asset', null)->toArray();

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('asset');
        Manifest::fromArray($data);
    }

    public function testRejectsDuplicateDataObjectEntriesForTheSameClassId(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('data-object_product_dup', 'dataObject', 'PR')->toArray();

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('dataObject:PR');
        Manifest::fromArray($data);
    }

    public function testAcceptsDataObjectEntriesWithDifferentClassIds(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('data-object_customer', 'dataObject', 'CU')->toArray();

        $restored = Manifest::fromArray($data);

        $this->assertCount(2, $restored->getIndices());
    }

    public function testRejectsIndexEntryFileNotMatchingShortName(): void
    {
        $data = $this->manifest()->toArray();
        $entry = $this->indexEntry('asset', 'asset', null)->toArray();
        $entry['file'] = 'document.ndjson.gz';
        $data['indices'][] = $entry;

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('must match its "short_name"');
        Manifest::fromArray($data);
    }

    public function testRejectsDuplicateShortName(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('data-object_product', 'dataObject', 'CU')->toArray();

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('data-object_product');
        Manifest::fromArray($data);
    }

    public function testRejectsDataObjectEntryWithoutClassIdNotNamedAsTheFolderIndex(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('data-object_product', 'dataObject', null)->toArray();

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('data-object_product');
        Manifest::fromArray($data);
    }

    public function testRejectsDataObjectEntryNamedAsTheFolderIndexWithAClassId(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('data-object-folder', 'dataObject', 'PR')->toArray();

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('data-object-folder');
        Manifest::fromArray($data);
    }

    public function testRejectsAssetEntryWithAClassId(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('asset', 'asset', 'PR')->toArray();

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('class_id');
        Manifest::fromArray($data);
    }

    public function testRejectsAssetEntryNotNamedAsset(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('assets', 'asset', null)->toArray();

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('assets');
        Manifest::fromArray($data);
    }

    public function testAcceptsValidFolderAndClassEntries(): void
    {
        $data = $this->manifest()->toArray();
        $data['indices'][] = $this->indexEntry('data-object-folder', 'dataObject', null)->toArray();

        $restored = Manifest::fromArray($data);

        $this->assertCount(2, $restored->getIndices());
        $this->assertNotNull($restored->getIndex('data-object-folder'));
        $this->assertNotNull($restored->getIndex('data-object_product'));
    }

    private function indexEntry(string $shortName, string $elementType, ?string $classId): ManifestIndex
    {
        return new ManifestIndex(
            shortName: $shortName,
            elementType: $elementType,
            classId: $classId,
            sourceIndex: 'pimcore_' . $shortName,
            documentCount: 1,
            file: $shortName . '.ndjson.gz',
            bytes: 1,
            sha256: str_repeat('b', 64),
        );
    }

    private function manifest(): Manifest
    {
        return new Manifest(
            createdAt: '2026-09-10T02:00:00+00:00',
            genericDataIndexVersion: '2.5.12',
            pimcoreVersion: '12.3.10',
            clientType: 'openSearch',
            indexPrefix: 'pimcore_',
            queueEntriesBefore: 12,
            queueEntriesAfter: 15,
            durationSeconds: 412,
            classMappingChecksums: ['PR' => 1830112233],
            indices: [
                new ManifestIndex(
                    shortName: 'data-object_product',
                    elementType: 'dataObject',
                    classId: 'PR',
                    sourceIndex: 'pimcore_data-object_product-even',
                    documentCount: 602114,
                    file: 'data-object_product.ndjson.gz',
                    bytes: 188432110,
                    sha256: str_repeat('a', 64),
                ),
            ],
        );
    }
}
