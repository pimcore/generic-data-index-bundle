<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Search\Document;

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
