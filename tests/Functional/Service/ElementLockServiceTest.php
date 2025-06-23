<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Service;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\ElementLockServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\Asset;
use Pimcore\Tests\Support\Util\TestHelper;

class ElementLockServiceTest extends Unit
{
    protected IndexTester $tester;

    private ElementLockServiceInterface $lockService;

    protected function _before(): void
    {
        $this->lockService = $this->tester->grabService(ElementLockServiceInterface::class);
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testElementIsLocked(): void
    {
        $asset1 = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $folder1 = TestHelper::createAssetFolder();
        $folder2 = TestHelper::createAssetFolder();
        $folder1
            ->setKey('test-folder')
            ->setLocked('propagate')
            ->save();
        $asset1
            ->setParent($folder1)
            ->setKey('test-asset-1')
            ->save();
        $folder2
            ->setParent($folder1)
            ->setKey('test-folder-2')
            ->save();
        $asset2
            ->setParent($folder2)
            ->setKey('test-asset-2')
            ->save();

        // Path /test-folder/test-asset-1
        // Path /test-folder/test-folder-2/test-asset-2

        $this->assetIsLocked($asset1);
        $this->assetIsLocked($folder2);
        $this->assetIsLocked($asset2);

        $folder1->unlockPropagate();

        $this->assetIsUnlocked($asset1);
        $this->assetIsUnlocked($folder2);
        $this->assetIsUnlocked($asset2);

        $folder2
            ->setLocked('self')
            ->save();

        $this->assetIsUnlocked($asset1);
        $this->assetIsLocked($folder1); // marked locked because of folder 2
        $this->assetIsUnlocked($asset2);
    }

    private function assetIsLocked(Asset $asset): void
    {
        $this->assertTrue($this->lockService->isElementLocked(
            $asset->getFullPath(),
            IndexName::ASSET->value,
            $asset->getLocked())
        );
    }

    private function assetIsUnlocked(Asset $asset): void
    {
        $this->assertFalse($this->lockService->isElementLocked(
            $asset->getFullPath(),
            IndexName::ASSET->value,
            $asset->getLocked())
        );
    }
}
