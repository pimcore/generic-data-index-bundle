<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\ValidationFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\PaginatedSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
abstract class AbstractSearchService implements SearchServiceInterface
{
    protected ValidatorInterface $validator;
    protected PaginationInfoServiceInterface $paginationInfoService;
    protected SearchIndexServiceInterface $searchIndexService;
    protected SearchModifierServiceInterface $searchModifierService;

    /**
     * @throws ValidationFailedException
     */
    protected function validateSearchModel(SearchInterface $search): self
    {

        $errors = $this->validator->validate($search);

        foreach($search->getModifiers() as $modifier) {
            $errors->addAll($this->validator->validate($modifier));
        }

        if (count($errors) > 0) {
            throw new ValidationFailedException($search, $errors);
        }

        return $this;
    }

    /**
     * @throws ValidationFailedException
     */
    protected function searchWithPagination(PaginatedSearchInterface $search, string $indexName): SearchResult
    {
        $this->validateSearchModel($search);
        $adapterSearch = $this->searchIndexService->createPaginatedSearch($search->getPage(), $search->getPageSize());
        $this->applyModifiersFromSearch($search, $adapterSearch);

        return $this
            ->searchIndexService
            ->search($adapterSearch, $indexName);
    }

    protected function applyModifiersFromSearch(
        SearchInterface $search,
        AdapterSearchInterface $openSearchSearch
    ): self
    {
        $this->searchModifierService->applyModifiersFromSearch(
            $this,
            $search,
            $openSearchSearch
        );

        return $this;
    }


    #[Required]
    public function setServices(
        PaginationInfoServiceInterface $paginationInfoService,
        ValidatorInterface $validator,
        SearchIndexServiceInterface $searchIndexService,
        SearchModifierServiceInterface $searchModifierService,
    ): AbstractSearchService
    {
        $this->paginationInfoService = $paginationInfoService;
        $this->validator = $validator;
        $this->searchIndexService = $searchIndexService;
        $this->searchModifierService = $searchModifierService;

        return $this;
    }
}
