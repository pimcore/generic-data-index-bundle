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
        $report = $this->checker()->check($this->manifest([$this->simple->getId() => $this->realChecksum]));

        $this->assertTrue($report->isCompatible());
        $this->assertSame(ClassCompatibilityStatus::COMPATIBLE, $report->statusOf($this->simple->getId()));
    }

    public function testStaleStoreButMatchingDefinitionIsCompatible(): void
    {
        $this->settingsStore->storeClassMapping($this->simple->getId(), 12345);

        $report = $this->checker()->check($this->manifest([$this->simple->getId() => $this->realChecksum]));

        $this->assertTrue($report->isCompatible());
        $this->assertSame(ClassCompatibilityStatus::STALE_STORE, $report->statusOf($this->simple->getId()));
    }

    public function testDoubleMismatchIsIncompatible(): void
    {
        $report = $this->checker()->check($this->manifest([$this->simple->getId() => 999]));

        $this->assertFalse($report->isCompatible());
        $this->assertSame([$this->simple->getId()], $report->incompatibleClassIds());
    }

    public function testStoredMatchButChangedDefinitionIsIncompatible(): void
    {
        // The stored checksum can lag an asynchronously dispatched class-mapping update. Even
        // when the stored value matches the manifest, the computed checksum from the current
        // class definition is what decides compatibility.
        $this->settingsStore->storeClassMapping($this->simple->getId(), 999);

        $report = $this->checker()->check($this->manifest([$this->simple->getId() => 999]));

        $this->assertFalse($report->isCompatible());
        $this->assertSame([$this->simple->getId()], $report->incompatibleClassIds());
    }

    public function testClassMissingLocallyIsReportedNotFatal(): void
    {
        $report = $this->checker()->check($this->manifest(['NOPE' => 1]));

        $this->assertTrue($report->isCompatible());
        $this->assertSame(ClassCompatibilityStatus::MISSING_LOCALLY, $report->statusOf('NOPE'));
    }

    public function testLocalClassAbsentFromManifestIsListed(): void
    {
        $report = $this->checker()->check($this->manifest([]));

        $this->assertContains('simple', $report->missingInManifest);
    }

    public function testNumericClassIdKeyIsHandledAsString(): void
    {
        // a numeric-string class id key ('12') is coerced to int(12) by PHP once it passes
        // through the manifest's class_mapping_checksums array; check() must cast it back to
        // string before comparing/looking up, or ClassDefinition::getById() TypeErrors under
        // strict_types
        $report = $this->checker()->check($this->manifest(['12' => 1]));

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
}
