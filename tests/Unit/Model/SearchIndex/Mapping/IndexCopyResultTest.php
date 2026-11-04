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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\SearchIndex\Mapping;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexCopyResult;

/**
 * @internal
 */
final class IndexCopyResultTest extends Unit
{
    public function testAResultNeedsAtLeastOneAttempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IndexCopyResult(ReindexResult::SUCCESS, attempts: 0);
    }

    public function testAResultReportsItsFields(): void
    {
        $result = new IndexCopyResult(ReindexResult::SUCCESS, ['standard_fields.a'], ['standard_fields.b'], 2);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(['standard_fields.a'], $result->getStrippedPaths());
        $this->assertSame(['standard_fields.b'], $result->getKeptPaths());
        $this->assertSame(2, $result->getAttempts());
    }
}
