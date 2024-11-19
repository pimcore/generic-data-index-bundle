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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\RequiredByFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\HitData;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\FetchIdsBySearchServiceInterface;
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
        private TransformToAdapterSearchServiceInterface $transformToAdapterSearchService,
    ) {
    }

    /**
     * @return HitData[]
     */
    public function getDependencyList(ElementInterface $element): array
    {
        $search = $this->searchProvider->createElementSearch();
        $search->addModifier(
            new RequiredByFilter(
                $element->getId(),
                $this->elementService->getElementType($element)
            )
        );

        return $this->fetchIdsService->fetchAllTypesAndIds(
            $this->transformToAdapterSearchService->transform($search),
            $this->indexNameResolver->resolveIndexName($search)
        );
    }
}
