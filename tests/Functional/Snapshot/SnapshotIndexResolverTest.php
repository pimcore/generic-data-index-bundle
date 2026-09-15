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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotIndexResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\DataObject\ClassDefinition;

final class SnapshotIndexResolverTest extends Unit
{
    protected IndexTester $tester;

    public function testResolvesFolderClassAssetAndDocumentTargets(): void
    {
        /** @var SnapshotIndexResolverInterface $resolver */
        $resolver = $this->tester->grabService(SnapshotIndexResolverInterface::class);
        $targets = $resolver->resolveAll();
        $byShortName = [];
        foreach ($targets as $target) {
            $this->assertInstanceOf(IndexTarget::class, $target);
            $byShortName[$target->shortName] = $target;
        }

        $this->assertArrayHasKey('asset', $byShortName);
        $this->assertArrayHasKey('document', $byShortName);
        $this->assertArrayHasKey('data-object-folder', $byShortName);
        $this->assertArrayHasKey('data-object_simple', $byShortName);
        $simple = $byShortName['data-object_simple'];
        $this->assertTrue($simple->isClassIndex());
        $this->assertSame(ClassDefinition::getByName('simple')->getId(), $simple->getClassId());
        $this->assertStringEndsWith('data-object_simple', $simple->aliasName);
        $this->assertSame('dataObject', $simple->elementType);
        $this->assertFalse($byShortName['asset']->isClassIndex());
    }

    public function testManifestEntryMapsBackByClassIdNotByName(): void
    {
        /** @var SnapshotIndexResolverInterface $resolver */
        $resolver = $this->tester->grabService(SnapshotIndexResolverInterface::class);
        $classId = ClassDefinition::getByName('simple')->getId();

        $target = $resolver->resolveManifestIndex(new ManifestIndex('renamed_on_source', 'dataObject', $classId, 'x-even', 0, 'f', 0, ''));
        $this->assertNotNull($target);
        $this->assertSame('data-object_simple', $target->shortName);

        $this->assertNull($resolver->resolveManifestIndex(new ManifestIndex('class_gone', 'dataObject', 'NOPE', 'x', 0, 'f', 0, '')));
        $this->assertNull($resolver->resolveManifestIndex(new ManifestIndex('weird', 'video', null, 'x', 0, 'f', 0, '')));
        $this->assertSame('asset', $resolver->resolveManifestIndex(new ManifestIndex('asset', 'asset', null, 'x', 0, 'f', 0, ''))->shortName);
        $this->assertSame('data-object-folder', $resolver->resolveManifestIndex(new ManifestIndex('data-object-folder', 'dataObject', null, 'x', 0, 'f', 0, ''))->shortName);
    }
}
