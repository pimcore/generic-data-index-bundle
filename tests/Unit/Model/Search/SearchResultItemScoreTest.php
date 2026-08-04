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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Search;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ScoreAwareResultItemInterface;

/**
 * Guards that the query-time relevance score is exposed on every element search-result item via
 * ScoreAwareResultItemInterface (deliberately separate from ElementSearchResultItemInterface, so
 * existing implementations of that interface stay backwards compatible): it defaults to null
 * (query produced no score) and round-trips via the setter. The hydration wiring that populates
 * it from the adapter hit is covered by SearchHelperScoreHydrationTest.
 *
 * @internal
 */
final class SearchResultItemScoreTest extends Unit
{
    public function testAssetItemScoreDefaultsToNullAndRoundTrips(): void
    {
        $item = new AssetSearchResultItem();

        self::assertInstanceOf(ScoreAwareResultItemInterface::class, $item);
        self::assertNull($item->getScore());
        self::assertSame(0.8734, $item->setScore(0.8734)->getScore());
        self::assertNull($item->setScore(null)->getScore());
    }

    public function testDataObjectItemScoreDefaultsToNullAndRoundTrips(): void
    {
        $item = new DataObjectSearchResultItem();

        self::assertInstanceOf(ScoreAwareResultItemInterface::class, $item);
        self::assertNull($item->getScore());
        self::assertSame(0.8734, $item->setScore(0.8734)->getScore());
        self::assertNull($item->setScore(null)->getScore());
    }

    public function testDocumentItemScoreDefaultsToNullAndRoundTrips(): void
    {
        $item = new DocumentSearchResultItem();

        self::assertInstanceOf(ScoreAwareResultItemInterface::class, $item);
        self::assertNull($item->getScore());
        self::assertSame(0.8734, $item->setScore(0.8734)->getScore());
        self::assertNull($item->setScore(null)->getScore());
    }
}
