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
        $this->assertSame(1, $restored->formatVersion);
        $this->assertSame(['PR' => 1830112233], $restored->classMappingChecksums);
        $index = $restored->getIndex('class_product');
        $this->assertInstanceOf(ManifestIndex::class, $index);
        $this->assertSame('PR', $index->classId);
        $this->assertSame(602114, $index->documentCount);
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
                    shortName: 'class_product',
                    elementType: 'dataObject',
                    classId: 'PR',
                    sourceIndex: 'pimcore_class_product-even',
                    documentCount: 602114,
                    file: 'class_product.ndjson.gz',
                    bytes: 188432110,
                    sha256: str_repeat('a', 64),
                ),
            ],
        );
    }
}
