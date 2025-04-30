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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\DefaultSearch\Search\Document;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;

/**
 * @internal
 */
final class DocumentSearchTest extends Unit
{
    public function testDocumentSearch()
    {
        $searchModifierMock1 = $this->makeEmpty(SearchModifierInterface::class);
        $searchModifierMock2 = $this->makeEmpty(SearchModifierInterface::class);
        $assetSearch = new DocumentSearch();
        $assetSearch->addModifier($searchModifierMock1);
        $assetSearch->addModifier($searchModifierMock2);

        $this->assertCount(2, $assetSearch->getModifiers());
        $this->assertSame([
            $searchModifierMock1,
            $searchModifierMock2,
        ], $assetSearch->getModifiers());
    }
}
