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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch\Search\Modifier\Source;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Source\SystemFieldsOnlySource;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Source\SourceHandlers;

/**
 * @internal
 */
final class SourceHandlersTest extends Unit
{
    public function testDataObjectSearchFetchesOnlyTheSystemFieldsAndTheInheritedFieldOrigins(): void
    {
        $adapterSearch = $this->applySystemFieldsOnlySource(new DataObjectSearch());

        $this->assertSame(
            ['system_fields', 'standard_fields._inherited_fields'],
            $adapterSearch->getSource()
        );
        $this->assertSame(
            ['system_fields', 'standard_fields._inherited_fields'],
            $adapterSearch->toArray()['_source']
        );
    }

    /**
     * Asset and document search results do not lazy-load their search index data from the
     * system fields only search, so the modifier must not restrict their source.
     */
    public function testAssetAndDocumentSearchesKeepTheFullSource(): void
    {
        $this->assertNull($this->applySystemFieldsOnlySource(new AssetSearch())->getSource());
        $this->assertNull($this->applySystemFieldsOnlySource(new DocumentSearch())->getSource());
    }

    private function applySystemFieldsOnlySource(SearchInterface $originalSearch): Search
    {
        $adapterSearch = new Search();

        (new SourceHandlers())->handleSystemFieldsOnlySource(
            new SystemFieldsOnlySource(),
            new SearchModifierContext($adapterSearch, $originalSearch)
        );

        return $adapterSearch;
    }
}
