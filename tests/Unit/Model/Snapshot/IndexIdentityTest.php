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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexIdentity;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;

final class IndexIdentityTest extends Unit
{
    public function testForClassBuildsADataObjectClassIndex(): void
    {
        $identity = IndexIdentity::forClass('PR');

        $this->assertTrue($identity->isDataObject());
        $this->assertTrue($identity->isClassIndex());
        $this->assertFalse($identity->isDataObjectFolder());
        $this->assertSame('PR', $identity->getClassId());
    }

    public function testForDataObjectFolderBuildsTheFolderIdentity(): void
    {
        $identity = IndexIdentity::forDataObjectFolder();

        $this->assertTrue($identity->isDataObject());
        $this->assertFalse($identity->isClassIndex());
        $this->assertTrue($identity->isDataObjectFolder());
        $this->assertNull($identity->getClassId());
    }

    public function testForAssetBuildsTheAssetIdentity(): void
    {
        $identity = IndexIdentity::forAsset();

        $this->assertTrue($identity->isAsset());
        $this->assertFalse($identity->isDataObject());
        $this->assertFalse($identity->isDocument());
    }

    public function testForDocumentBuildsTheDocumentIdentity(): void
    {
        $identity = IndexIdentity::forDocument();

        $this->assertTrue($identity->isDocument());
        $this->assertFalse($identity->isDataObject());
        $this->assertFalse($identity->isAsset());
    }

    public function testFromManifestIndexCarriesElementTypeAndClassId(): void
    {
        $identity = IndexIdentity::fromManifestIndex($this->manifestIndex('data-object_product', 'dataObject', 'PR'));

        $this->assertTrue($identity->isClassIndex());
        $this->assertSame('PR', $identity->getClassId());
    }

    public function testAssetAcceptsOnlyItsOwnShortName(): void
    {
        $identity = IndexIdentity::forAsset();

        $this->assertTrue($identity->acceptsShortName('asset'));
        $this->assertFalse($identity->acceptsShortName('assets'));
        $this->assertSame('asset', $identity->expectedShortName());
    }

    public function testDocumentAcceptsOnlyItsOwnShortName(): void
    {
        $identity = IndexIdentity::forDocument();

        $this->assertTrue($identity->acceptsShortName('document'));
        $this->assertFalse($identity->acceptsShortName('documents'));
        $this->assertSame('document', $identity->expectedShortName());
    }

    public function testDataObjectFolderAcceptsOnlyItsOwnShortName(): void
    {
        $identity = IndexIdentity::forDataObjectFolder();

        $this->assertTrue($identity->acceptsShortName('data-object-folder'));
        $this->assertFalse($identity->acceptsShortName('data-object_product'));
        $this->assertSame('data-object-folder', $identity->expectedShortName());
    }

    public function testClassIndexAcceptsAnyPrefixedNameExceptTheFolderName(): void
    {
        $identity = IndexIdentity::forClass('PR');

        $this->assertTrue($identity->acceptsShortName('data-object_product'));
        $this->assertTrue($identity->acceptsShortName('data-object_anything'));
        $this->assertFalse($identity->acceptsShortName('asset'));
        $this->assertFalse($identity->acceptsShortName('data-object-folder'));
        $this->assertNull($identity->expectedShortName());
    }

    public function testKeyDistinguishesEveryKind(): void
    {
        $dataObjectPrefix = ElementType::DATA_OBJECT->value . ':';
        $this->assertSame($dataObjectPrefix . 'PR', IndexIdentity::forClass('PR')->key());
        $this->assertSame($dataObjectPrefix, IndexIdentity::forDataObjectFolder()->key());
        $this->assertSame(ElementType::ASSET->value, IndexIdentity::forAsset()->key());
        $this->assertSame(ElementType::DOCUMENT->value, IndexIdentity::forDocument()->key());
        $this->assertNotSame(IndexIdentity::forClass('PR')->key(), IndexIdentity::forClass('CU')->key());
    }

    public function testEqualsComparesElementTypeAndClassId(): void
    {
        $this->assertTrue(IndexIdentity::forClass('PR')->equals(IndexIdentity::forClass('PR')));
        $this->assertFalse(IndexIdentity::forClass('PR')->equals(IndexIdentity::forClass('CU')));
        $this->assertFalse(IndexIdentity::forClass('PR')->equals(IndexIdentity::forDataObjectFolder()));
        $this->assertFalse(IndexIdentity::forAsset()->equals(IndexIdentity::forDocument()));
    }

    public function testElementTypeEnumResolvesKnownTypes(): void
    {
        $this->assertSame(ElementType::ASSET, IndexIdentity::forAsset()->elementTypeEnum());
        $this->assertSame(ElementType::DOCUMENT, IndexIdentity::forDocument()->elementTypeEnum());
        $this->assertSame(ElementType::DATA_OBJECT, IndexIdentity::forClass('PR')->elementTypeEnum());
    }

    public function testElementTypeEnumThrowsForUnknownElementType(): void
    {
        $identity = new IndexIdentity('carrierPigeon');

        $this->expectException(InvalidSnapshotException::class);
        $this->expectExceptionMessage('carrierPigeon');
        $identity->elementTypeEnum();
    }

    private function manifestIndex(string $shortName, string $elementType, ?string $classId): ManifestIndex
    {
        return new ManifestIndex(
            shortName: $shortName,
            elementType: $elementType,
            classId: $classId,
            sourceIndex: 'pimcore_' . $shortName,
            documentCount: 1,
            file: $shortName . '.ndjson.gz',
            bytes: 1,
            sha256: str_repeat('a', 64),
        );
    }
}
