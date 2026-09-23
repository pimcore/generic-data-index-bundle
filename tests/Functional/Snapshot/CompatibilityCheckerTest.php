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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\CompatibilityCheckerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\DataObject\ClassDefinition;

final class CompatibilityCheckerTest extends Unit
{
    protected IndexTester $tester;

    private SettingsStoreServiceInterface $settingsStore;

    private DataObjectIndexHandler $handler;

    private ClassDefinition $simple;

    private int $realChecksum;

    protected function _before(): void
    {
        $this->settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        $this->handler = $this->tester->grabService(DataObjectIndexHandler::class);
        $this->simple = ClassDefinition::getByName('simple');
        $this->realChecksum = $this->handler->getClassMappingCheckSum($this->handler->getMappingProperties($this->simple));
        $this->settingsStore->storeClassMapping($this->simple->getId(), $this->realChecksum);
    }

    protected function _after(): void
    {
        $this->settingsStore->storeClassMapping($this->simple->getId(), $this->realChecksum);
    }

    public function testStoreMatchIsCompatible(): void
    {
        $report = $this->checker()->check($this->manifestWithIndices([$this->simple->getId() => $this->realChecksum]));

        $this->assertTrue($report->isCompatible());
        $this->assertSame(ClassCompatibilityStatus::COMPATIBLE, $report->statusOf($this->simple->getId()));
    }

    public function testStaleStoreButMatchingDefinitionIsCompatible(): void
    {
        $this->settingsStore->storeClassMapping($this->simple->getId(), 12345);

        $report = $this->checker()->check($this->manifestWithIndices([$this->simple->getId() => $this->realChecksum]));

        $this->assertTrue($report->isCompatible());
        $this->assertSame(ClassCompatibilityStatus::STALE_STORE, $report->statusOf($this->simple->getId()));
    }

    public function testDoubleMismatchIsIncompatible(): void
    {
        $report = $this->checker()->check($this->manifestWithIndices([$this->simple->getId() => 999]));

        $this->assertFalse($report->isCompatible());
        $this->assertSame([$this->simple->getId()], $report->incompatibleClassIds());
    }

    public function testStoredMatchButChangedDefinitionIsIncompatible(): void
    {
        // The stored checksum can lag an asynchronously dispatched class-mapping update. Even
        // when the stored value matches the manifest, the computed checksum from the current
        // class definition is what decides compatibility.
        $this->settingsStore->storeClassMapping($this->simple->getId(), 999);

        $report = $this->checker()->check($this->manifestWithIndices([$this->simple->getId() => 999]));

        $this->assertFalse($report->isCompatible());
        $this->assertSame([$this->simple->getId()], $report->incompatibleClassIds());
    }

    public function testClassMissingLocallyIsReportedNotFatal(): void
    {
        $report = $this->checker()->check($this->manifestWithIndices(['NOPE' => 1]));

        $this->assertTrue($report->isCompatible());
        $this->assertSame(ClassCompatibilityStatus::MISSING_LOCALLY, $report->statusOf('NOPE'));
    }

    public function testLocalClassAbsentFromManifestIsListed(): void
    {
        $report = $this->checker()->check($this->manifest([]));

        $this->assertContains('simple', $report->getMissingInManifest());
    }

    public function testClassWithIndexEntryButNoChecksumIsNotMissingInManifest(): void
    {
        // An index entry with no checksum makes the class UNVERIFIED, but it does have an index
        // in the snapshot, so it must not also be reported as "missing in manifest".
        $manifest = $this->manifest([]);
        $index = new ManifestIndex(
            'data-object_simple', 'dataObject', $this->simple->getId(), 'pimcore_simple-1',
            3, 'data-object_simple.ndjson.gz', 123, 'deadbeef'
        );

        $report = $this->checker()->check($manifest->withIndices([$index]));

        $this->assertNotContains('simple', $report->getMissingInManifest());
        $this->assertSame(ClassCompatibilityStatus::UNVERIFIED, $report->statusOf($this->simple->getId()));
    }

    public function testClassWithChecksumButNoIndexEntryIsMissingInManifest(): void
    {
        // A class_mapping_checksums entry alone does not mean the snapshot actually holds an
        // index for the class: without a matching index entry, the old local index is left
        // untouched by the import, which the operator needs to know about via missingInManifest.
        $report = $this->checker()->check($this->manifest([$this->simple->getId() => $this->realChecksum]));

        $this->assertContains('simple', $report->getMissingInManifest());
    }

    public function testNumericClassIdKeyIsHandledAsString(): void
    {
        // a numeric-string class id key ('12') is coerced to int(12) by PHP once it passes
        // through the manifest's class_mapping_checksums array; check() must cast it back to
        // string before comparing/looking up, or ClassDefinition::getById() TypeErrors under
        // strict_types
        $report = $this->checker()->check($this->manifestWithIndices(['12' => 1]));

        $this->assertSame(ClassCompatibilityStatus::MISSING_LOCALLY, $report->statusOf('12'));
    }

    public function testIndexWithoutManifestChecksumIsUnverified(): void
    {
        $manifest = $this->manifest([]);
        $index = new ManifestIndex(
            'data-object_simple', 'dataObject', $this->simple->getId(), 'pimcore_simple-1',
            3, 'data-object_simple.ndjson.gz', 123, 'deadbeef'
        );

        $report = $this->checker()->check($manifest->withIndices([$index]));

        $this->assertSame(ClassCompatibilityStatus::UNVERIFIED, $report->statusOf($this->simple->getId()));
        $this->assertFalse($report->isCompatible());
    }

    public function testIndexForClassMissingLocallyWithoutManifestChecksumIsMissingLocallyNotUnverified(): void
    {
        // A class id that no longer exists locally must not gate the import as UNVERIFIED: the
        // importer already skips such an index unconditionally ("no local counterpart"), so the
        // compatibility gate must agree it's harmless, independent of --force.
        $manifest = $this->manifest([]);
        $index = new ManifestIndex(
            'data-object_nope', 'dataObject', 'NOPE', 'pimcore_nope-1',
            3, 'data-object_nope.ndjson.gz', 123, 'deadbeef'
        );

        $report = $this->checker()->check($manifest->withIndices([$index]));

        $this->assertSame(ClassCompatibilityStatus::MISSING_LOCALLY, $report->statusOf('NOPE'));
        $this->assertTrue($report->isCompatible());
    }

    private function checker(): CompatibilityCheckerInterface
    {
        return $this->tester->grabService(CompatibilityCheckerInterface::class);
    }

    private function manifest(array $checksums): Manifest
    {
        return new Manifest('2026-09-10T00:00:00+00:00', 'dev', 'dev', 'openSearch', 'pimcore_', 0, 0, 0, $checksums, []);
    }

    /**
     * A manifest that, unlike manifest(), also carries a class index entry for every checksum
     * key, which is what makes the compatibility gate apply to that class at all.
     */
    private function manifestWithIndices(array $checksums): Manifest
    {
        $indices = [];
        foreach (array_keys($checksums) as $classId) {
            $shortName = 'data-object_' . strtolower((string) $classId);
            $indices[] = new ManifestIndex(
                $shortName, 'dataObject', (string) $classId, 'pimcore_' . $shortName . '-odd',
                1, $shortName . '.ndjson.gz', 1, str_repeat('0', 64),
            );
        }

        return $this->manifest($checksums)->withIndices($indices);
    }

    public function testChecksumEntryWithoutIndexEntryDoesNotGateTheImport(): void
    {
        // The import never touches an index the manifest does not carry, so a mismatching
        // checksum for such a class must not block everything else: the class is reported as
        // missing in the manifest (its old local index stays as it is) and nothing more.
        $report = $this->checker()->check(
            $this->manifest([$this->simple->getId() => $this->realChecksum + 1]),
        );

        $this->assertTrue($report->isCompatible());
        $this->assertNull($report->statusOf((string) $this->simple->getId()));
        $this->assertContains('simple', $report->getMissingInManifest());
    }
}
