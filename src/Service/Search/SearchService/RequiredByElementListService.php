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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\ElementSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\RequiredByFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\HitData;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\FetchIdsBySearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final readonly class RequiredByElementListService implements RequiredByElementListServiceInterface
{
    public function __construct(
        private ElementServiceInterface $elementService,
        private FetchIdsBySearchServiceInterface $fetchIdsService,
        private IndexNameResolverInterface $indexNameResolver,
        private SearchProviderInterface $searchProvider,
        private SearchIndexServiceInterface $searchIndexService,
        private TransformToAdapterSearchServiceInterface $transformToAdapterSearchService,
    ) {
    }

    /**
     * @return HitData[]
     */
    public function getDependencyList(ElementInterface $element, ?ElementSearchInterface $search = null): array
    {
        $sortById = false;
        $search = $search ?? $this->searchProvider->createElementSearch();
        $search->addModifier(
            new RequiredByFilter(
                $element->getId(),
                $this->elementService->getElementType($element)
            )
        );
        $adapterSearch = $this->transformToAdapterSearchService->transform($search);
        if ($adapterSearch->getSortList()->isEmpty()) {
            $sortById = true;
        }

        return $this->fetchIdsService->fetchAllTypesAndIds(
            $adapterSearch,
            $this->indexNameResolver->resolveIndexName($search),
            $sortById
        );
    }

    /**
     * @return HitData[]
     */
    public function getDependencyListForCurrentPage(
        ElementInterface $element,
        ElementSearchInterface $search
    ): array {
        $search->addModifier(
            new RequiredByFilter(
                $element->getId(),
                $this->elementService->getElementType($element)
            )
        );

        $adapterSearch = $this->transformToAdapterSearchService->transform($search);
        $adapterSearch = clone $adapterSearch;
        $adapterSearch->setSource([SystemField::ELEMENT_TYPE->getPath()]);
        $searchResult = $this->searchIndexService->search(
            $adapterSearch,
            $this->indexNameResolver->resolveIndexName($search)
        );

        return array_map(
            static fn (SearchResultHit $item) =>
            new HitData(
                id: $item->getId(),
                elementType: $item->getSource()[FieldCategory::SYSTEM_FIELDS->value][SystemField::ELEMENT_TYPE->value],
                index: $item->getIndex(),
            ),
            $searchResult->getHits()
        );
    }
}
