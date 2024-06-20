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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

/**
 * @internal
 */
final readonly class TransformToAdapterSearchService implements TransformToAdapterSearchServiceInterface
{
    public function __construct(
        private IndexNameResolverInterface $indexNameResolver,
        private Asset\SearchHelper $assetSearchHelper,
        private DataObject\SearchHelper $dataObjectSearchHelper,
        private Document\SearchHelper $documentSearchHelper,
        private Element\ElementSearchHelperInterface $elementSearchHelper,
    ) {
    }

    public function transform(SearchInterface $search, bool $enableOrderByPageNumber = false): AdapterSearchInterface
    {
        $index = $this->indexNameResolver->resolveIndexName($search);

        return match(true) {
            $search instanceof AssetSearch
                => $this->assetSearchHelper->createAdapterSearch($search, $index, $enableOrderByPageNumber),
            $search instanceof DataObjectSearch
                => $this->dataObjectSearchHelper->createAdapterSearch($search, $index, $enableOrderByPageNumber),
            $search instanceof DocumentSearch
                => $this->documentSearchHelper->createAdapterSearch($search, $index, $enableOrderByPageNumber),
            $search instanceof ElementSearch
                => $this->elementSearchHelper->createAdapterSearch($search, $index, $enableOrderByPageNumber),
            default => throw new InvalidArgumentException('Unsupported search type ' . get_class($search)),
        };
    }
}
